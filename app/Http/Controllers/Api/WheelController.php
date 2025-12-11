<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WheelSector;
use App\Models\WheelSetting;
use App\Models\Spin;
use App\Models\User;
use App\Models\UserTicket;
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

            // Проверяем режим "всегда пусто"
            $settings = WheelSetting::getSettings();
            $winningSector = null;
            
            if ($settings->always_empty_mode) {
                // Режим "всегда пусто" - выбираем случайный пустой сектор
                $emptySectors = WheelSector::where('is_active', true)
                    ->where('prize_type', 'empty')
                    ->get();
                
                if ($emptySectors->isEmpty()) {
                    Log::channel('wheel-errors')->error('No empty sectors configured in always_empty_mode', [
                        'telegram_id' => $telegramId,
                        'user_id' => $user->id,
                        'settings' => [
                            'always_empty_mode' => $settings->always_empty_mode,
                        ],
                    ]);
                    return response()->json([
                        'error' => 'No empty sectors configured'
                    ], 500);
                }
                
                $winningSector = $emptySectors->random();
            } else {
                // Обычный режим - выбираем сектор на основе вероятностей
                $winningSector = WheelSector::getRandomSector();
            }
            
            if (!$winningSector) {
                Log::channel('wheel-errors')->error('No active sectors configured', [
                    'telegram_id' => $telegramId,
                    'user_id' => $user->id,
                    'settings' => [
                        'always_empty_mode' => $settings->always_empty_mode,
                    ],
                ]);
                Log::error('No active sectors configured', [
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'No active sectors configured',
                    'message' => 'Ошибка конфигурации рулетки. Обратитесь к администратору.'
                ], 500);
            }

            // Валидация сектора перед использованием
            if (!$winningSector->sector_number || !$winningSector->prize_type) {
                Log::channel('wheel-errors')->error('Invalid sector data', [
                    'telegram_id' => $telegramId,
                    'user_id' => $user->id,
                    'sector_id' => $winningSector->id,
                    'sector_number' => $winningSector->sector_number,
                    'prize_type' => $winningSector->prize_type,
                ]);
                Log::error('Invalid sector data', [
                    'user_id' => $user->id,
                    'sector_id' => $winningSector->id,
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid sector data',
                    'message' => 'Ошибка конфигурации сектора. Обратитесь к администратору.'
                ], 500);
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

                // Если выигрыш - начисляем приз
                $prizeAwarded = false;
                
                // Обрабатываем действие из типа приза, если оно указано
                if ($prizeType && $prizeType->action === 'add_ticket') {
                    $ticketsToAdd = $prizeType->value ?? 1;
                    // ВАЖНО: Обновляем данные пользователя из БД перед начислением
                    $user->refresh();
                    $ticketsBeforePrize = $user->tickets_available;
                    
                    // Логируем начисление билетов ДО изменения
                    Log::info('Adding tickets from prize type action (BEFORE)', [
                        'request_id' => $requestId,
                        'user_id' => $user->id,
                        'telegram_id' => $telegramId,
                        'sector_number' => $winningSector->sector_number,
                        'prize_type_id' => $prizeType->id,
                        'tickets_to_add' => $ticketsToAdd,
                        'tickets_before_prize' => $ticketsBeforePrize,
                        'tickets_before_deduction' => $ticketsBeforeDeduction,
                        'expected_final' => $ticketsBeforeDeduction - 1 + $ticketsToAdd,
                    ]);
                    
                    $user->tickets_available = $user->tickets_available + $ticketsToAdd;
                    
                    if ($user->tickets_available > 0) {
                        $user->tickets_depleted_at = null;
                    }
                    
                    $user->save();
                    $prizeAwarded = true;
                    
                    // Логируем начисление билетов ПОСЛЕ изменения
                    Log::info('Ticket added from prize type action (AFTER)', [
                        'request_id' => $requestId,
                        'user_id' => $user->id,
                        'telegram_id' => $telegramId,
                        'sector_number' => $winningSector->sector_number,
                        'prize_type_id' => $prizeType->id,
                        'tickets_added' => $ticketsToAdd,
                        'tickets_before_prize' => $ticketsBeforePrize,
                        'tickets_after_prize' => $user->tickets_available,
                        'tickets_before_deduction' => $ticketsBeforeDeduction,
                        'expected_final' => $ticketsBeforeDeduction - 1 + $ticketsToAdd,
                        'actual_final' => $user->fresh()->tickets_available,
                        'timestamp' => now()->toIso8601String(),
                    ]);
                }
                
                // ВАЖНО: Проверяем что сектор действительно выпал и приз должен быть выдан
                if ($winningSector->prize_type === 'empty') {
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
                    // Пользователь должен связаться с администратором для получения приза.
                    // Приз сохраняется в таблице spins с правильным prize_value.
                    $user->total_wins++;
                    $user->save();
                    $prizeAwarded = true;
                    
                    Log::info('Money prize awarded (NOT automatically credited - user must contact admin)', [
                        'user_id' => $user->id,
                        'sector_number' => $winningSector->sector_number,
                        'prize_value' => $winningSector->prize_value, // Используем напрямую из сектора - гарантированно правильное значение
                        'note' => 'Money is NOT added to user balance automatically',
                    ]);
                    
                    // ПРИМЕЧАНИЕ: Уведомления о выигрыше отправляются отдельным endpoint
                    // после завершения анимации на фронтенде (4 секунды)
                } elseif ($winningSector->prize_type === 'ticket' && $winningSector->prize_value > 0) {
                    // ВАЖНО: Начисляем билет(ы) за выигрыш
                    // prize_value должно соответствовать конфигурации сектора из админки
                    // Максимальное значение контролируется валидацией в админке (max:10)
                    // Если приз 500 билетов не задан в админке, он не может выпасть
                    $ticketsToAdd = $winningSector->prize_value ?? 1;
                    
                    // ДОПОЛНИТЕЛЬНАЯ ПРОВЕРКА: Если значение больше 10, логируем предупреждение
                    if ($ticketsToAdd > 10) {
                        Log::channel('wheel-errors')->warning('Unusual ticket prize value detected', [
                            'telegram_id' => $telegramId,
                            'user_id' => $user->id,
                            'sector_number' => $winningSector->sector_number,
                            'prize_value' => $ticketsToAdd,
                            'note' => 'Prize value exceeds recommended maximum of 10 tickets',
                        ]);
                        Log::warning('Unusual ticket prize value detected', [
                            'user_id' => $user->id,
                            'sector_number' => $winningSector->sector_number,
                            'prize_value' => $ticketsToAdd,
                            'note' => 'Prize value exceeds recommended maximum of 10 tickets',
                        ]);
                    }
                    
                    // ВАЖНО: Обновляем данные пользователя из БД перед начислением
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
                    
                    $user->tickets_available = $user->tickets_available + $ticketsToAdd;
                    
                    // Ограничиваем максимальное количество билетов (если есть лимит)
                    // Пока без лимита, но можно добавить
                    
                    // Если билеты стали больше 0, сбрасываем точку восстановления и флаг показа pop-up
                    // (потому что билеты больше не закончились)
                    if ($user->tickets_available > 0) {
                        $user->tickets_depleted_at = null;
                        // Сбрасываем флаг показа pop-up, чтобы он мог появиться снова при следующем обнулении
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
                        'note' => 'Tickets were successfully added to user balance',
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
                    
                    Log::info('Secret box or sponsor gift prize awarded (NOT automatically credited - user must contact admin)', [
                        'user_id' => $user->id,
                        'sector_number' => $winningSector->sector_number,
                        'prize_type' => $winningSector->prize_type,
                        'note' => 'Prize is NOT awarded automatically',
                    ]);
                } else {
                    // Неизвестный тип приза или некорректное значение
                    Log::channel('wheel-errors')->warning('Unknown prize type or invalid value', [
                        'telegram_id' => $telegramId,
                        'user_id' => $user->id,
                        'spin_id' => $spin->id ?? null,
                        'sector_number' => $winningSector->sector_number,
                        'prize_type' => $winningSector->prize_type,
                        'prize_value' => $winningSector->prize_value,
                    ]);
                    Log::warning('Unknown prize type or invalid value', [
                        'user_id' => $user->id,
                        'sector_number' => $winningSector->sector_number,
                        'prize_type' => $winningSector->prize_type,
                        'prize_value' => $winningSector->prize_value,
                    ]);
                }

                // Логируем финальное состояние билетов после всех операций
                $user->refresh(); // Обновляем данные из БД для точности
                $duration = round((microtime(true) - $startTime) * 1000, 2); // в миллисекундах
                
                Log::info('Spin transaction completed', [
                    'request_id' => $requestId,
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                    'spin_id' => $spin->id ?? null,
                    'tickets_final' => $user->tickets_available,
                    'prize_awarded' => $prizeAwarded,
                    'sector_number' => $winningSector->sector_number,
                    'prize_type' => $winningSector->prize_type,
                    'duration_ms' => $duration,
                    'timestamp' => now()->toIso8601String(),
                ]);
                
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
                Log::channel('wheel-errors')->error('Transaction error in wheel spin', [
                    'telegram_id' => $telegramId ?? null,
                    'user_id' => $user->id ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            // Логируем в отдельный файл для ошибок пользовательской части
            Log::channel('wheel-errors')->error('Error in wheel spin', [
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
            Log::error('Error in wheel spin', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

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

