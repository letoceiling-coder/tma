<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WheelSector;
use App\Models\WheelSetting;
use App\Models\Spin;
use App\Models\User;
use App\Models\UserTicket;
use App\Models\WheelError;
use App\Services\TelegramService;
use App\Services\TelegramNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WheelController extends Controller
{
    /**
     * Получить конфигурацию рулетки
     * 
     * @return JsonResponse
     */
    public function getConfig(): JsonResponse
    {
        $sectors = WheelSector::getActiveSectors();
        $settings = WheelSetting::getSettings();
        
        return response()->json([
            'sectors' => $sectors->map(function ($sector) {
                return [
                    'id' => $sector->id,
                    'sector_number' => $sector->sector_number,
                    'prize_type' => $sector->prize_type,
                    'prize_value' => $sector->prize_value,
                    'icon_url' => $sector->icon_url,
                    'probability_percent' => (float) $sector->probability_percent,
                ];
            }),
            'total_probability' => (float) $sectors->sum('probability_percent'),
            'settings' => [
                'admin_username' => $settings->admin_username,
            ],
        ]);
    }

    /**
     * Запустить прокрут рулетки
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function spin(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $requestId = uniqid('spin_', true);
        
        try {
            $initData = $request->header('X-Telegram-Init-Data') ?? $request->query('initData');
            
            // Улучшенное логирование запроса
            Log::info('Spin request received', [
                'request_id' => $requestId,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'has_init_data' => !empty($initData),
                'init_data_length' => $initData ? strlen($initData) : 0,
                'timestamp' => now()->toIso8601String(),
            ]);
            
            if (!$initData || trim($initData) === '') {
                Log::warning('Spin request without initData', [
                    'request_id' => $requestId,
                    'ip' => $request->ip(),
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Init data not provided',
                    'message' => 'Ошибка авторизации. Пожалуйста, перезагрузите приложение.'
                ], 401);
            }

            $telegramId = TelegramService::getTelegramId($initData);
            
            if (!$telegramId) {
                Log::warning('Spin request with invalid initData', [
                    'request_id' => $requestId,
                    'ip' => $request->ip(),
                    'init_data_preview' => substr($initData, 0, 50) . '...',
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'User ID not found',
                    'message' => 'Ошибка авторизации. Пожалуйста, перезагрузите приложение.'
                ], 401);
            }

            // Получаем настройки для определения количества стартовых билетов
            $settings = WheelSetting::getSettings();
            $initialTicketsCount = $settings->initial_tickets_count ?? 1; // По умолчанию 1 билет

            // Найти или создать пользователя
            $user = User::firstOrCreate(
                ['telegram_id' => $telegramId],
                [
                    'name' => 'Telegram User',
                    'email' => "telegram_{$telegramId}@telegram.local",
                    'password' => bcrypt(str()->random(32)),
                    'tickets_available' => $initialTicketsCount, // Используем настройку из админки
                    'stars_balance' => 0,
                    'total_spins' => 0,
                    'total_wins' => 0,
                ]
            );
            
            // Если это новый пользователь, создаем запись в user_tickets
            if ($user->wasRecentlyCreated) {
                UserTicket::create([
                    'user_id' => $user->id,
                    'tickets_count' => $initialTicketsCount,
                    'restored_at' => null,
                    'source' => 'initial_bonus',
                ]);
                
                Log::info('Initial tickets granted to new user (from spin)', [
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                    'initial_tickets_count' => $initialTicketsCount,
                ]);
            }

            // Логируем начальное состояние билетов
            Log::info('Spin request processing', [
                'request_id' => $requestId,
                'user_id' => $user->id,
                'telegram_id' => $telegramId,
                'tickets_before_spin' => $user->tickets_available,
                'timestamp' => now()->toIso8601String(),
            ]);

            // Проверка наличия билетов
            if ($user->tickets_available <= 0) {
                Log::warning('Spin attempt with no tickets', [
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                    'tickets_available' => $user->tickets_available,
                ]);
                return response()->json([
                    'error' => 'No tickets available',
                    'message' => 'У вас нет доступных билетов'
                ], 400);
            }
            
            // Защита от повторного запуска: проверяем, что прошло достаточно времени с последнего спина
            // Минимальный интервал между спинами - 3 секунды (защита от двойных кликов)
            if ($user->last_spin_at && $user->last_spin_at->diffInSeconds(now()) < 3) {
                $secondsSinceLastSpin = $user->last_spin_at->diffInSeconds(now());
                Log::warning('Spin attempt too soon after last spin', [
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                    'seconds_since_last_spin' => $secondsSinceLastSpin,
                    'last_spin_at' => $user->last_spin_at->toIso8601String(),
                ]);
                
                // Логируем в таблицу wheel_errors
                try {
                    WheelError::logError(
                        'duplicate_spin_attempt',
                        "Spin attempt too soon after last spin: {$secondsSinceLastSpin} seconds",
                        [
                            'seconds_since_last_spin' => $secondsSinceLastSpin,
                            'last_spin_at' => $user->last_spin_at->toIso8601String(),
                            'telegram_id' => $telegramId,
                        ],
                        $user->id
                    );
                } catch (\Exception $e) {
                    Log::error('Failed to log wheel error to database', ['error' => $e->getMessage()]);
                }
                
                return response()->json([
                    'success' => false,
                    'error' => 'Spin in progress',
                    'message' => 'Пожалуйста, подождите завершения предыдущей прокрутки'
                ], 429); // 429 Too Many Requests
            }

            // Проверяем режим "всегда пусто"
            $settings = WheelSetting::getSettings();
            $winningSector = null;
            
            // ТЕСТОВЫЙ РЕЖИМ: Принудительное выпадение конкретного сектора
            // Используется для диагностики проблем с выпадением секторов
            $testSectorId = $request->input('test_sector_id');
            $testMode = !empty($testSectorId);
            
            if ($testMode) {
                Log::info('TEST MODE: Forcing sector selection', [
                    'request_id' => $requestId,
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                    'test_sector_id' => $testSectorId,
                ]);
                
                $winningSector = WheelSector::where('id', $testSectorId)
                    ->where('is_active', true)
                    ->first();
                
                if (!$winningSector) {
                    $errorMsg = "Test sector not found or inactive: {$testSectorId}";
                    Log::channel('wheel-errors')->error($errorMsg, [
                        'request_id' => $requestId,
                        'telegram_id' => $telegramId,
                        'user_id' => $user->id,
                        'test_sector_id' => $testSectorId,
                    ]);
                    
                    try {
                        WheelError::logError(
                            'test_mode_error',
                            $errorMsg,
                            [
                                'test_sector_id' => $testSectorId,
                                'telegram_id' => $telegramId,
                            ],
                            $user->id,
                            $testSectorId
                        );
                    } catch (\Exception $e) {
                        Log::error('Failed to log wheel error to database', ['error' => $e->getMessage()]);
                    }
                    
                    return response()->json([
                        'success' => false,
                        'error' => 'Test sector not found',
                        'message' => 'Тестовый сектор не найден или неактивен'
                    ], 404);
                }
                
                Log::info('TEST MODE: Sector selected', [
                    'request_id' => $requestId,
                    'sector_id' => $winningSector->id,
                    'sector_number' => $winningSector->sector_number,
                    'prize_type' => $winningSector->prize_type,
                    'prize_value' => $winningSector->prize_value,
                ]);
            } elseif ($settings->always_empty_mode) {
                // Режим "всегда пусто" - выбираем случайный пустой сектор
                $emptySectors = WheelSector::where('is_active', true)
                    ->where('prize_type', 'empty')
                    ->get();
                
                if ($emptySectors->isEmpty()) {
                    $errorMsg = 'No empty sectors configured in always_empty_mode';
                    Log::channel('wheel-errors')->error($errorMsg, [
                        'telegram_id' => $telegramId,
                        'user_id' => $user->id,
                        'settings' => [
                            'always_empty_mode' => $settings->always_empty_mode,
                        ],
                    ]);
                    
                    // Логируем в таблицу wheel_errors
                    try {
                        WheelError::logError(
                            'configuration_error',
                            $errorMsg,
                            [
                                'always_empty_mode' => $settings->always_empty_mode,
                                'telegram_id' => $telegramId,
                            ],
                            $user->id
                        );
                    } catch (\Exception $e) {
                        Log::error('Failed to log wheel error to database', ['error' => $e->getMessage()]);
                    }
                    
                    return response()->json([
                        'error' => 'No empty sectors configured'
                    ], 500);
                }
                
                $winningSector = $emptySectors->random();
            } else {
                // Обычный режим - выбираем сектор на основе вероятностей
                // Включаем детальное логирование для диагностики sponsor_gift
                $enableDetailedLogging = config('app.debug') || $request->input('debug', false);
                $sectorResult = WheelSector::getRandomSector($enableDetailedLogging);
                
                // Извлекаем данные из результата
                $winningSector = $sectorResult['sector'] ?? null;
                $randomValue = $sectorResult['random_value'] ?? null;
                $expectedResult = $sectorResult['expected_result'] ?? null;
                
                // Сохраняем диагностические данные для логирования ошибок
                $diagnosticData = [
                    'random_value' => $randomValue,
                    'expected_result' => $expectedResult,
                ];
                
                // Если getRandomSector вернул null, это означает выпадение пустого сектора
                // (остаток вероятности от 0 до 100%)
                if (!$winningSector) {
                    // Находим любой активный пустой сектор для отображения
                    $emptySectors = WheelSector::where('is_active', true)
                        ->where('prize_type', 'empty')
                        ->orderBy('sector_number')
                        ->get();
                    
                    if ($emptySectors->isEmpty()) {
                        // Если нет пустых секторов, это ошибка конфигурации
                        $errorMsg = 'Empty sector selected but no empty sectors configured';
                        Log::channel('wheel-errors')->error($errorMsg, [
                            'telegram_id' => $telegramId,
                            'user_id' => $user->id,
                            'total_probability' => WheelSector::getActiveSectors()->sum('probability_percent'),
                            'random_value' => $randomValue,
                            'expected_result' => $expectedResult,
                        ]);
                        
                        // Логируем в таблицу wheel_errors
                        try {
                            WheelError::logError(
                                'configuration_error',
                                $errorMsg,
                                [
                                    'total_probability' => WheelSector::getActiveSectors()->sum('probability_percent'),
                                    'telegram_id' => $telegramId,
                                ],
                                $user->id,
                                null,
                                null,
                                $randomValue,
                                $expectedResult
                            );
                        } catch (\Exception $e) {
                            Log::error('Failed to log wheel error to database', ['error' => $e->getMessage()]);
                        }
                        
                        // Используем первый активный сектор как fallback (не должно происходить)
                        $fallbackSector = WheelSector::where('is_active', true)->first();
                        if ($fallbackSector) {
                            $winningSector = $fallbackSector;
                            Log::warning('Using fallback sector for empty result', [
                                'sector_id' => $fallbackSector->id,
                                'sector_number' => $fallbackSector->sector_number,
                            ]);
                        } else {
                            // Критическая ошибка - нет активных секторов
                            return response()->json([
                                'success' => false,
                                'error' => 'No active sectors configured',
                                'message' => 'Ошибка конфигурации рулетки. Обратитесь к администратору.'
                            ], 500);
                        }
                    } else {
                        // Выбираем случайный пустой сектор для отображения
                        $winningSector = $emptySectors->random();
                        Log::info('Empty sector selected (probability remainder)', [
                            'request_id' => $requestId,
                            'user_id' => $user->id,
                            'telegram_id' => $telegramId,
                            'sector_number' => $winningSector->sector_number,
                            'total_probability' => WheelSector::getActiveSectors()->sum('probability_percent'),
                            'random_value' => $randomValue,
                            'expected_result' => $expectedResult,
                        ]);
                    }
                }
            }
            
            if (!$winningSector) {
                $errorMsg = 'No active sectors configured';
                Log::channel('wheel-errors')->error($errorMsg, [
                    'telegram_id' => $telegramId,
                    'user_id' => $user->id,
                    'settings' => [
                        'always_empty_mode' => $settings->always_empty_mode,
                    ],
                ]);
                Log::error($errorMsg, [
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                ]);
                
                // Логируем в таблицу wheel_errors
                try {
                    WheelError::logError(
                        'configuration_error',
                        $errorMsg,
                        [
                            'always_empty_mode' => $settings->always_empty_mode,
                            'telegram_id' => $telegramId,
                        ],
                        $user->id
                    );
                } catch (\Exception $e) {
                    Log::error('Failed to log wheel error to database', ['error' => $e->getMessage()]);
                }
                
                return response()->json([
                    'success' => false,
                    'error' => 'No active sectors configured',
                    'message' => 'Ошибка конфигурации рулетки. Обратитесь к администратору.'
                ], 500);
            }

            // Валидация сектора перед использованием
            if (!$winningSector->sector_number || !$winningSector->prize_type) {
                $errorMsg = 'Invalid sector data: missing sector_number or prize_type';
                Log::channel('wheel-errors')->error($errorMsg, [
                    'request_id' => $requestId,
                    'telegram_id' => $telegramId,
                    'user_id' => $user->id,
                    'sector_id' => $winningSector->id ?? null,
                    'sector_number' => $winningSector->sector_number ?? null,
                    'prize_type' => $winningSector->prize_type ?? null,
                    'random_value' => $randomValue ?? null,
                    'expected_result' => $expectedResult ?? null,
                ]);
                Log::error($errorMsg, [
                    'user_id' => $user->id,
                    'sector_id' => $winningSector->id ?? null,
                ]);
                
                // Логируем в таблицу wheel_errors
                try {
                    WheelError::logError(
                        'sector_validation_error',
                        $errorMsg,
                        [
                            'sector_id' => $winningSector->id ?? null,
                            'sector_number' => $winningSector->sector_number ?? null,
                            'prize_type' => $winningSector->prize_type ?? null,
                            'telegram_id' => $telegramId,
                        ],
                        $user->id,
                        $winningSector->id ?? null,
                        $winningSector->prize_type ?? null,
                        $randomValue ?? null,
                        $expectedResult ?? null
                    );
                } catch (\Exception $e) {
                    Log::error('Failed to log wheel error to database', ['error' => $e->getMessage()]);
                }
                
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid sector data',
                    'message' => 'Ошибка конфигурации сектора. Обратитесь к администратору.'
                ], 500);
            }
            
            // Проверяем корректность суммы вероятностей перед началом транзакции
            $probabilityValidation = WheelSector::validateProbabilities();
            if (!$probabilityValidation['valid']) {
                $errorMsg = $probabilityValidation['message'];
                Log::channel('wheel-errors')->error('Probability validation failed', [
                    'telegram_id' => $telegramId,
                    'user_id' => $user->id,
                    'validation' => $probabilityValidation,
                ]);
                
                // Логируем в таблицу wheel_errors
                try {
                    WheelError::logError(
                        'probability_error',
                        $errorMsg,
                        [
                            'validation' => $probabilityValidation,
                            'telegram_id' => $telegramId,
                        ],
                        $user->id,
                        null,
                        null,
                        $randomValue ?? null,
                        $expectedResult ?? null
                    );
                } catch (\Exception $e) {
                    Log::error('Failed to log wheel error to database', ['error' => $e->getMessage()]);
                }
                
                // Не блокируем прокрутку, но логируем ошибку
                // Продолжаем выполнение, так как getRandomSector уже обработал эту ситуацию
            }

            // Создаем запись о прокруте
            DB::beginTransaction();
            
            try {
                // ВАЖНО: Блокируем строку пользователя для защиты от race condition
                // Это предотвращает параллельное списание билетов при одновременных запросах
                $user = User::where('id', $user->id)->lockForUpdate()->first();
                
                if (!$user) {
                    DB::rollBack();
                    Log::channel('wheel-errors')->error('User not found after lock', [
                        'telegram_id' => $telegramId,
                        'user_id' => $user->id ?? null,
                    ]);
                    return response()->json([
                        'success' => false,
                        'error' => 'User not found',
                        'message' => 'Ошибка при обработке запроса'
                    ], 404);
                }
                
                // Повторная проверка билетов после блокировки (на случай параллельных запросов)
                $ticketsBeforeDeduction = $user->tickets_available;
                if ($ticketsBeforeDeduction <= 0) {
                    DB::rollBack();
                    Log::warning('Spin attempt with no tickets (after lock)', [
                        'user_id' => $user->id,
                        'telegram_id' => $telegramId,
                        'tickets_available' => $ticketsBeforeDeduction,
                        'note' => 'Tickets were depleted by another request',
                    ]);
                    return response()->json([
                        'error' => 'No tickets available',
                        'message' => 'У вас нет доступных билетов'
                    ], 400);
                }
                
                // Уменьшаем количество билетов
                $user->tickets_available = max(0, $ticketsBeforeDeduction - 1);
                $user->last_spin_at = now();
                // Сбрасываем флаг уведомления при новой прокрутке
                $user->last_notification_sent_at = null;
                $user->total_spins++;
                
                // Получаем интервал восстановления билетов из настроек
                $restoreIntervalSeconds = ($settings->ticket_restore_hours ?? 3) * 3600;
                
                // НОВАЯ ЛОГИКА: Когда билеты заканчиваются (становятся 0), фиксируем момент времени
                // tickets_depleted_at = now() - это момент, когда билеты закончились
                // От этого момента через интервал (ticket_restore_hours) будет восстановлен 1 билет
                if ($user->tickets_available === 0) {
                    $user->tickets_depleted_at = now(); // Фиксируем момент окончания билетов
                }
                
                // Логируем списание билета ДО сохранения
                Log::info('Ticket deducted for spin (BEFORE save)', [
                    'request_id' => $requestId,
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                    'tickets_before_deduction' => $ticketsBeforeDeduction,
                    'tickets_after_deduction' => $user->tickets_available,
                    'tickets_deducted' => 1,
                    'timestamp' => now()->toIso8601String(),
                ]);
                
                $user->save();
                
                // Логируем списание билета ПОСЛЕ сохранения
                Log::info('Ticket deducted for spin (AFTER save)', [
                    'request_id' => $requestId,
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                    'tickets_available_in_db' => $user->fresh()->tickets_available,
                    'timestamp' => now()->toIso8601String(),
                ]);

                // Загружаем тип приза, если он связан с сектором
                $prizeType = null;
                $prizeMessage = null;
                if ($winningSector->prize_type_id) {
                    $prizeType = \App\Models\PrizeType::find($winningSector->prize_type_id);
                    // Получаем сообщение из типа приза, если оно указано
                    if ($prizeType && $prizeType->message) {
                        $prizeMessage = $prizeType->message;
                    }
                }

                // Сохраняем результат прокрута
                $spin = Spin::create([
                    'user_id' => $user->id,
                    'spin_time' => now(),
                    'prize_type' => $winningSector->prize_type,
                    'prize_value' => $winningSector->prize_value,
                    'sector_id' => $winningSector->id,
                    'sector_number' => $winningSector->sector_number, // Сохраняем номер сектора для админки
                ]);

                // ВАЖНО: Начисление приза происходит строго по типу приза сектора
                // Объединяем логику в одно место, чтобы избежать двойного начисления
                $prizeAwarded = false;
                $ticketsToAdd = 0; // Количество билетов для начисления (0 если не билет)
                
                // Определяем, нужно ли начислять билеты
                // Проверяем prize_type сектора ИЛИ action типа приза
                if ($winningSector->prize_type === 'ticket' && $winningSector->prize_value > 0) {
                    // Приз - билет из сектора
                    $ticketsToAdd = $winningSector->prize_value ?? 1;
                } elseif ($prizeType && $prizeType->action === 'add_ticket') {
                    // Приз - билет из типа приза (action)
                    $ticketsToAdd = $prizeType->value ?? 1;
                }
                
                // ВАЖНО: Начисляем билеты ТОЛЬКО ОДИН РАЗ, если нужно
                if ($ticketsToAdd > 0) {
                    // Обновляем данные пользователя из БД перед начислением
                    $user->refresh();
                    $ticketsBeforePrize = $user->tickets_available;
                    
                    // Логируем начисление билетов ДО изменения
                    Log::info('Adding tickets from prize (BEFORE)', [
                        'request_id' => $requestId,
                        'user_id' => $user->id,
                        'telegram_id' => $telegramId,
                        'sector_number' => $winningSector->sector_number,
                        'prize_type' => $winningSector->prize_type,
                        'tickets_to_add' => $ticketsToAdd,
                        'tickets_before_prize' => $ticketsBeforePrize,
                        'tickets_before_deduction' => $ticketsBeforeDeduction,
                        'expected_final' => $ticketsBeforeDeduction - 1 + $ticketsToAdd,
                    ]);
                    
                    // Формула: newTickets = oldTickets - 1 + ticketsToAdd
                    $user->tickets_available = $user->tickets_available + $ticketsToAdd;
                    
                    // Если билеты стали больше 0, сбрасываем точку восстановления и флаг показа pop-up
                    if ($user->tickets_available > 0) {
                        $user->tickets_depleted_at = null;
                        $user->referral_popup_shown_at = null;
                    }
                    
                    $user->save();
                    $prizeAwarded = true;
                    
                    // Логируем начисление билетов ПОСЛЕ изменения
                    Log::info('Ticket prize awarded and credited to user (AFTER)', [
                        'request_id' => $requestId,
                        'user_id' => $user->id,
                        'telegram_id' => $telegramId,
                        'sector_number' => $winningSector->sector_number,
                        'prize_type' => $winningSector->prize_type,
                        'tickets_added' => $ticketsToAdd,
                        'tickets_before_prize' => $ticketsBeforePrize,
                        'tickets_after_prize' => $user->tickets_available,
                        'tickets_before_deduction' => $ticketsBeforeDeduction,
                        'expected_final' => $ticketsBeforeDeduction - 1 + $ticketsToAdd,
                        'actual_final' => $user->fresh()->tickets_available,
                        'timestamp' => now()->toIso8601String(),
                    ]);
                } elseif ($winningSector->prize_type === 'empty') {
                    // Пустой сектор - приз не начисляется
                    $prizeAwarded = false;
                    Log::info('Empty sector - no prize awarded', [
                        'user_id' => $user->id,
                        'sector_number' => $winningSector->sector_number,
                    ]);
                } elseif ($winningSector->prize_type === 'money' && $winningSector->prize_value > 0) {
                    // ВАЖНО: Денежный приз (300, 500 рублей и т.д.) - НЕ начисляется автоматически!
                    // Деньги НЕ добавляются в баланс пользователя.
                    // Только отмечаем выигрыш в статистике (total_wins++).
                    $user->total_wins++;
                    $user->save();
                    $prizeAwarded = true;
                    
                    Log::info('Money prize awarded (NOT automatically credited - user must contact admin)', [
                        'user_id' => $user->id,
                        'sector_number' => $winningSector->sector_number,
                        'prize_value' => $winningSector->prize_value,
                        'note' => 'Money is NOT added to user balance automatically',
                    ]);
                } elseif ($winningSector->prize_type === 'secret_box' || $winningSector->prize_type === 'sponsor_gift') {
                    // ВАЖНО: Секретный бокс или подарок от спонсора - НЕ начисляется автоматически!
                    // Никакие призы не добавляются автоматически.
                    // Только отмечаем выигрыш в статистике.
                    // Пользователь должен связаться с администратором для получения приза.
                    if (!$prizeAwarded) {
                        $user->total_wins++;
                        $user->save();
                        $prizeAwarded = true;
                    }
                    
                    // ДЕТАЛЬНОЕ ЛОГИРОВАНИЕ для диагностики sponsor_gift
                    Log::info('Secret box or sponsor gift prize awarded (NOT automatically credited - user must contact admin)', [
                        'request_id' => $requestId,
                        'user_id' => $user->id,
                        'telegram_id' => $telegramId,
                        'spin_id' => $spin->id ?? null,
                        'sector_id' => $winningSector->id,
                        'sector_number' => $winningSector->sector_number,
                        'prize_type' => $winningSector->prize_type,
                        'prize_value' => $winningSector->prize_value,
                        'prize_type_id' => $winningSector->prize_type_id,
                        'is_sponsor_gift' => $winningSector->prize_type === 'sponsor_gift',
                        'probability_percent' => (float) $winningSector->probability_percent,
                        'is_active' => $winningSector->is_active,
                        'icon_url' => $winningSector->icon_url,
                        'prize_awarded' => $prizeAwarded,
                        'total_wins' => $user->total_wins,
                        'note' => 'Prize is NOT awarded automatically',
                        'test_mode' => $testMode ?? false,
                    ]);
                    
                    // Дополнительное логирование в wheel-errors для sponsor_gift
                    if ($winningSector->prize_type === 'sponsor_gift') {
                        Log::channel('wheel-errors')->info('SPONSOR_GIFT sector selected and processed', [
                            'request_id' => $requestId,
                            'user_id' => $user->id,
                            'telegram_id' => $telegramId,
                            'spin_id' => $spin->id ?? null,
                            'sector_id' => $winningSector->id,
                            'sector_number' => $winningSector->sector_number,
                            'probability_percent' => (float) $winningSector->probability_percent,
                            'is_active' => $winningSector->is_active,
                            'test_mode' => $testMode ?? false,
                        ]);
                    }
                } else {
                    // Неизвестный тип приза или некорректное значение
                    $errorMsg = 'Unknown prize type or invalid value: ' . $winningSector->prize_type;
                    Log::channel('wheel-errors')->warning($errorMsg, [
                        'request_id' => $requestId,
                        'telegram_id' => $telegramId,
                        'user_id' => $user->id,
                        'spin_id' => $spin->id ?? null,
                        'sector_number' => $winningSector->sector_number,
                        'prize_type' => $winningSector->prize_type,
                        'prize_value' => $winningSector->prize_value,
                        'random_value' => $randomValue ?? null,
                        'expected_result' => $expectedResult ?? null,
                    ]);
                    Log::warning($errorMsg, [
                        'user_id' => $user->id,
                        'sector_number' => $winningSector->sector_number,
                        'prize_type' => $winningSector->prize_type,
                        'prize_value' => $winningSector->prize_value,
                    ]);
                    
                    // НЕ БЛОКИРУЕМ UI - продолжаем выполнение, но логируем ошибку
                    // Пользователь увидит результат прокрутки, но приз не будет начислен
                    // В логах будет зафиксирована ошибка для последующего исправления
                    try {
                        WheelError::logError(
                            'prize_award_error',
                            $errorMsg,
                            [
                                'spin_id' => $spin->id ?? null,
                                'sector_number' => $winningSector->sector_number,
                                'prize_type' => $winningSector->prize_type,
                                'prize_value' => $winningSector->prize_value,
                                'telegram_id' => $telegramId,
                                'random_value' => $randomValue ?? null,
                                'expected_result' => $expectedResult ?? null,
                            ],
                            $user->id,
                            $winningSector->id,
                            $winningSector->prize_type,
                            $randomValue ?? null,
                            $expectedResult ?? null
                        );
                    } catch (\Exception $logError) {
                        Log::error('Failed to log wheel error to database', ['error' => $logError->getMessage()]);
                    }
                    
                    // Продолжаем выполнение - не прерываем прокрутку
                    // Приз не начислен, но пользователь увидит результат
                }

                // Логируем финальное состояние билетов после всех операций
                $user->refresh(); // Обновляем данные из БД для точности
                $duration = round((microtime(true) - $startTime) * 1000, 2); // в миллисекундах
                
                // ВАЖНО: Проверяем правильность итогового количества билетов
                $expectedFinal = $ticketsBeforeDeduction - 1 + ($ticketsToAdd ?? 0);
                $actualFinal = $user->tickets_available;
                
                Log::info('Spin transaction completed', [
                    'request_id' => $requestId,
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                    'spin_id' => $spin->id ?? null,
                    'tickets_before_deduction' => $ticketsBeforeDeduction,
                    'tickets_deducted' => 1,
                    'tickets_added' => $ticketsToAdd ?? 0,
                    'expected_final' => $expectedFinal,
                    'tickets_final' => $actualFinal,
                    'calculation_correct' => $expectedFinal === $actualFinal,
                    'prize_awarded' => $prizeAwarded,
                    'sector_number' => $winningSector->sector_number,
                    'prize_type' => $winningSector->prize_type,
                    'prize_value' => $winningSector->prize_value,
                    'duration_ms' => $duration,
                    'timestamp' => now()->toIso8601String(),
                ]);
                
                // Если расчет неверный, логируем ошибку
                if ($expectedFinal !== $actualFinal) {
                    Log::channel('wheel-errors')->error('Ticket calculation mismatch!', [
                        'request_id' => $requestId,
                        'user_id' => $user->id,
                        'telegram_id' => $telegramId,
                        'tickets_before_deduction' => $ticketsBeforeDeduction,
                        'tickets_deducted' => 1,
                        'tickets_added' => $ticketsToAdd ?? 0,
                        'expected_final' => $expectedFinal,
                        'actual_final' => $actualFinal,
                        'difference' => $actualFinal - $expectedFinal,
                    ]);
                }
                
                DB::commit();

                // Рассчитываем время до следующего билета
                $secondsUntilNextTicket = null;
                $nextTicketAt = null;

                // НОВАЯ ЛОГИКА: tickets_depleted_at хранит момент, когда билеты закончились
                // Время до восстановления = tickets_depleted_at + интервал - now()
                // Таймер показывается только если билетов нет (0)
                if ($user->tickets_available === 0 && $user->tickets_depleted_at) {
                    $restoreTime = $user->tickets_depleted_at->copy()->addSeconds($restoreIntervalSeconds);
                    // Используем разницу timestamp для правильного расчета (если restore_time в будущем - положительное, если в прошлом - 0)
                    $secondsUntilNextTicket = max(0, $restoreTime->timestamp - now()->timestamp);
                    $nextTicketAt = $restoreTime->toIso8601String();
                }

                // ============================================
                // НОВАЯ ЛОГИКА: Точная остановка по центру сектора
                // ============================================
                //
                // Структура колеса:
                // - Сектор i (index 0-11) начинается с угла: -90° + i*30°
                // - Центр сектора i: -90° + i*30° + 15° = -75° + i*30°
                // - Указатель (pointer) находится строго сверху на -90°
                //
                // Цель: Повернуть колесо так, чтобы центр сектора i совпал с указателем
                // Уравнение: (центр_сектора_i + rotation) = угол_указателя
                // (-75° + i*30° + R) = -90°
                // R = -90° - (-75° + i*30°) = -90° + 75° - i*30° = -15° - i*30°
                // 
                // Для положительных углов: R = 360° - 15° - i*30° = 345° - i*30°
                //
                // Новая формула: normalizedRotation = 345 - (sectorIndex * 30)
                
                $segmentAngle = 360 / 12; // 30 градусов на сектор
                
                // Преобразуем sector_number (1-12) в индекс (0-11)
                $sectorIndex = $winningSector->sector_number - 1;
                
                // ФОРМУЛА для точного попадания в центр сектора
                $normalizedRotation = 345 - ($sectorIndex * $segmentAngle);
                
                // Нормализуем к диапазону 0-360
                $normalizedRotation = fmod($normalizedRotation, 360);
                if ($normalizedRotation < 0) {
                    $normalizedRotation += 360;
                }
                
                // Генерируем УНИКАЛЬНЫЙ большой rotation на основе:
                // 1. Количества спинов пользователя (для уникальности)
                // 2. Случайного числа оборотов (5-10)
                // 3. Normalized rotation для попадания в нужный сектор
                $userSpins = $user->total_spins; // Уже увеличен выше
                $randomSpins = rand(5, 10);
                
                // Базовый rotation = (номер_спина × 360 × 20) + (случайные_обороты × 360)
                // Это гарантирует что каждый rotation ВСЕГДА больше предыдущего
                $baseRotation = ($userSpins * 360 * 20) + ($randomSpins * 360);
                
                $targetRotation = $baseRotation + $normalizedRotation;
                
                // Логирование для отладки
                Log::debug('Wheel spin calculation', [
                    'sector_number' => $winningSector->sector_number,
                    'prize_type' => $winningSector->prize_type,
                    'prize_value' => $winningSector->prize_value,
                    'sector_index' => $sectorIndex,
                    'random_spins' => $randomSpins,
                    'normalized_rotation' => $normalizedRotation,
                    'target_rotation' => $targetRotation,
                    'verification_index' => floor((360 - $normalizedRotation + 15) / 30) % 12, // Должно быть равно $sectorIndex
                ]);

                // Финальная валидация данных перед отправкой
                if (!$spin->id) {
                    Log::channel('wheel-errors')->error('Spin ID is missing after creation', [
                        'telegram_id' => $telegramId,
                        'user_id' => $user->id,
                        'sector_number' => $winningSector->sector_number,
                    ]);
                    throw new \Exception('Failed to create spin record');
                }

                if ($targetRotation === null || !is_numeric($targetRotation)) {
                    Log::channel('wheel-errors')->error('Invalid rotation calculated', [
                        'telegram_id' => $telegramId,
                        'user_id' => $user->id,
                        'sector_number' => $winningSector->sector_number,
                        'target_rotation' => $targetRotation,
                    ]);
                    throw new \Exception('Failed to calculate rotation');
                }

                return response()->json([
                    'success' => true,
                    'spin_id' => $spin->id,
                    'sector' => [
                        'id' => $winningSector->id,
                        'sector_number' => $winningSector->sector_number,
                        'prize_type' => $winningSector->prize_type,
                        'prize_value' => $winningSector->prize_value,
                    ],
                    'rotation' => $targetRotation,
                    'tickets_available' => $user->tickets_available,
                    'prize_awarded' => $prizeAwarded,
                    // Сообщение из типа приза (если указано в админке)
                    'prize_message' => $prizeMessage,
                    // Информация о восстановлении билетов
                    'restore_interval_hours' => $settings->ticket_restore_hours ?? 3,
                    'restore_interval_seconds' => $restoreIntervalSeconds,
                    'next_ticket_at' => $nextTicketAt,
                    'seconds_until_next_ticket' => $secondsUntilNextTicket,
                    // Добавляем отладочную информацию для проверки
                    '_debug' => [
                        'sector_index' => $sectorIndex,
                        'random_spins' => $randomSpins,
                        'normalized_rotation' => round($normalizedRotation, 2),
                        'expected_frontend_index' => floor((360 - $normalizedRotation + 15) / 30) % 12,
                    ],
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                // Логируем ошибку транзакции
                $errorMsg = 'Transaction error in wheel spin: ' . $e->getMessage();
                Log::channel('wheel-errors')->error($errorMsg, [
                    'telegram_id' => $telegramId ?? null,
                    'user_id' => $user->id ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                
                // Логируем в таблицу wheel_errors
                try {
                    WheelError::logError(
                        'transaction_error',
                        $errorMsg,
                        [
                            'error_class' => get_class($e),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'telegram_id' => $telegramId ?? null,
                            'request_id' => $requestId ?? null,
                        ],
                        $user->id ?? null
                    );
                } catch (\Exception $logError) {
                    Log::error('Failed to log wheel error to database', ['error' => $logError->getMessage()]);
                }
                
                throw $e;
            }

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            // Определяем тип ошибки
            $errorType = 'unknown_error';
            if (str_contains($e->getMessage(), 'ticket')) {
                $errorType = 'ticket_error';
            } elseif (str_contains($e->getMessage(), 'sector') || str_contains($e->getMessage(), 'configuration')) {
                $errorType = 'configuration_error';
            } elseif (str_contains($e->getMessage(), 'probability')) {
                $errorType = 'probability_error';
            } elseif (str_contains($e->getMessage(), 'transaction')) {
                $errorType = 'transaction_error';
            }
            
            // Логируем в отдельный файл для ошибок пользовательской части
            $errorMsg = 'Error in wheel spin: ' . $e->getMessage();
            Log::channel('wheel-errors')->error($errorMsg, [
                'request_id' => $requestId,
                'telegram_id' => $telegramId ?? null,
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'duration_ms' => $duration,
                'request_data' => [
                    'ip' => $request->ip() ?? null,
                    'init_data_provided' => !empty($initData),
                    'init_data_length' => $initData ? strlen($initData) : 0,
                    'tickets_available' => $user->tickets_available ?? null,
                ],
            ]);

            // Также логируем в общий лог для совместимости
            Log::error($errorMsg, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Логируем в таблицу wheel_errors
            try {
                WheelError::logError(
                    $errorType,
                    $errorMsg,
                    [
                        'error_class' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'duration_ms' => $duration,
                        'request_id' => $requestId,
                        'ip' => $request->ip() ?? null,
                        'init_data_provided' => !empty($initData),
                        'tickets_available' => $user->tickets_available ?? null,
                    ],
                    $user->id ?? null
                );
            } catch (\Exception $logError) {
                Log::error('Failed to log wheel error to database', ['error' => $logError->getMessage()]);
            }

            // Определяем тип ошибки для более точного сообщения
            $errorMessage = 'Произошла ошибка при прокруте рулетки';
            $statusCode = 500;

            if (str_contains($e->getMessage(), 'ticket')) {
                $errorMessage = 'У вас нет доступных билетов';
                $statusCode = 400;
            } elseif (str_contains($e->getMessage(), 'sector') || str_contains($e->getMessage(), 'configuration')) {
                $errorMessage = 'Ошибка конфигурации рулетки. Обратитесь к администратору.';
                $statusCode = 500;
            }

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $errorMessage
            ], $statusCode);
        }
    }
}

