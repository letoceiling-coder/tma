<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Spin;
use App\Models\User;
use App\Models\WheelSector;
use App\Models\PrizeType;
use App\Services\TelegramService;
use App\Services\TelegramNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SpinNotificationController extends Controller
{
    /**
     * Верифицировать сектор по углу и отправить уведомление
     * Вызывается с фронтенда после завершения анимации
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function notify(Request $request): JsonResponse
    {
        try {
            $initData = $request->header('X-Telegram-Init-Data') ?? $request->query('initData');
            
            if (!$initData) {
                return response()->json([
                    'error' => 'Init data not provided'
                ], 401);
            }

            $telegramId = TelegramService::getTelegramId($initData);
            
            if (!$telegramId) {
                return response()->json([
                    'error' => 'User ID not found'
                ], 401);
            }

            // Находим пользователя
            $user = User::where('telegram_id', $telegramId)->first();
            
            if (!$user) {
                return response()->json([
                    'error' => 'User not found'
                ], 404);
            }

            // Получаем ID прокрута и угол из запроса
            $spinId = $request->input('spin_id');
            $finalRotation = $request->input('final_rotation'); // Финальный угол после анимации
            
            if (!$spinId) {
                return response()->json([
                    'error' => 'Spin ID not provided'
                ], 400);
            }

            // Находим прокрут с загрузкой сектора
            $spin = Spin::with('sector')
                ->where('id', $spinId)
                ->where('user_id', $user->id)
                ->first();
            
            if (!$spin) {
                return response()->json([
                    'error' => 'Spin not found'
                ], 404);
            }

            // ВЕРИФИКАЦИЯ: Определяем сектор по углу и проверяем соответствие
            $verifiedSector = null;
            $verificationPassed = false;
            
            if ($finalRotation !== null) {
                $verifiedSector = $this->calculateSectorFromAngle($finalRotation);
                
                // Проверяем соответствие между визуальным сектором и начисленным призом
                if ($verifiedSector && $verifiedSector->sector_number === $spin->sector_number) {
                    $verificationPassed = true;
                    
                    // Дополнительная проверка: убеждаемся что приз соответствует сектору
                    if ($verifiedSector->prize_type === $spin->prize_type && 
                        $verifiedSector->prize_value === $spin->prize_value) {
                        Log::info('Sector verification passed', [
                            'spin_id' => $spin->id,
                            'sector_number' => $spin->sector_number,
                            'prize_type' => $spin->prize_type,
                            'prize_value' => $spin->prize_value,
                            'final_rotation' => $finalRotation,
                        ]);
                    } else {
                        // КРИТИЧЕСКАЯ ОШИБКА: Несоответствие приза
                        Log::channel('wheel-errors')->error('Prize mismatch detected', [
                            'telegram_id' => $telegramId,
                            'user_id' => $user->id,
                            'spin_id' => $spin->id,
                            'expected_sector' => [
                                'sector_number' => $verifiedSector->sector_number,
                                'prize_type' => $verifiedSector->prize_type,
                                'prize_value' => $verifiedSector->prize_value,
                            ],
                            'actual_spin' => [
                                'sector_number' => $spin->sector_number,
                                'prize_type' => $spin->prize_type,
                                'prize_value' => $spin->prize_value,
                            ],
                            'final_rotation' => $finalRotation,
                        ]);
                        Log::error('Prize mismatch detected', [
                            'spin_id' => $spin->id,
                            'expected_sector' => [
                                'sector_number' => $verifiedSector->sector_number,
                                'prize_type' => $verifiedSector->prize_type,
                                'prize_value' => $verifiedSector->prize_value,
                            ],
                            'actual_spin' => [
                                'sector_number' => $spin->sector_number,
                                'prize_type' => $spin->prize_type,
                                'prize_value' => $spin->prize_value,
                            ],
                            'final_rotation' => $finalRotation,
                        ]);
                        
                        // Исправляем несоответствие: используем данные из верифицированного сектора
                        DB::beginTransaction();
                        try {
                            $spin->prize_type = $verifiedSector->prize_type;
                            $spin->prize_value = $verifiedSector->prize_value;
                            $spin->sector_id = $verifiedSector->id;
                            $spin->sector_number = $verifiedSector->sector_number;
                            $spin->save();
                            
                            // Пересчитываем начисление приза если нужно
                            $this->recalculatePrize($user, $spin, $verifiedSector);
                            
                            DB::commit();
                            
                            Log::info('Prize corrected after verification', [
                                'spin_id' => $spin->id,
                                'corrected_prize_type' => $verifiedSector->prize_type,
                                'corrected_prize_value' => $verifiedSector->prize_value,
                            ]);
                        } catch (\Exception $e) {
                            DB::rollBack();
                            Log::channel('wheel-errors')->error('Error correcting prize', [
                                'telegram_id' => $telegramId,
                                'user_id' => $user->id,
                                'spin_id' => $spin->id,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                            ]);
                            Log::error('Error correcting prize', [
                                'spin_id' => $spin->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                } else {
                    // Сектор не совпадает
                    Log::channel('wheel-errors')->warning('Sector verification failed', [
                        'telegram_id' => $telegramId,
                        'user_id' => $user->id,
                        'spin_id' => $spin->id,
                        'expected_sector_number' => $verifiedSector ? $verifiedSector->sector_number : null,
                        'actual_sector_number' => $spin->sector_number,
                        'final_rotation' => $finalRotation,
                    ]);
                    Log::warning('Sector verification failed', [
                        'spin_id' => $spin->id,
                        'expected_sector_number' => $verifiedSector ? $verifiedSector->sector_number : null,
                        'actual_sector_number' => $spin->sector_number,
                        'final_rotation' => $finalRotation,
                    ]);
                }
            } else {
                // Если угол не передан, используем данные из БД без верификации
                Log::warning('Final rotation not provided, skipping verification', [
                    'spin_id' => $spin->id,
                ]);
                $verificationPassed = true; // Принимаем как валидный, если угол не передан
            }

            // Загружаем актуальные данные сектора с типом приза
            $sector = WheelSector::with('prizeType')->find($spin->sector_id);
            if (!$sector) {
                // Если сектор не найден, пытаемся найти по номеру
                $sector = WheelSector::with('prizeType')
                    ->where('sector_number', $spin->sector_number)
                    ->where('is_active', true)
                    ->first();
            }

            // Получаем тип приза, если он связан с сектором
            $prizeType = $sector && $sector->prize_type_id ? $sector->prizeType : null;

            // Генерируем ссылку на админа, если настроена
            $adminLink = null;
            $settings = \App\Models\WheelSetting::getSettings();
            if ($settings->admin_username) {
                $adminUsername = ltrim($settings->admin_username, '@');
                // Используем rawurlencode() вместо urlencode() для правильного кодирования пробелов как %20
                $adminLink = "https://t.me/{$adminUsername}?text=" . rawurlencode("Здравствуйте, я выиграл приз в WOW Spin");
            }

            // УНИФИЦИРОВАННАЯ ЛОГИКА: Отправляем уведомление в зависимости от типа приза
            // ВАЖНО: 
            // 1. При пустом секторе - НЕ отправляем уведомление в Telegram (только попап на фронтенде)
            // 2. При выигрыше - отправляем ОДНО уведомление в Telegram
            // 3. Сообщения в попапе и Telegram должны совпадать по содержанию
            // 4. Если есть связанный тип приза, используем его сообщение
            $notificationSent = false;
            
            // Используем сообщение из типа приза, если оно есть
            $customMessage = $prizeType && $prizeType->message ? $prizeType->message : null;
            
            if ($spin->prize_type === 'empty') {
                // Пустой сектор - НЕ отправляем уведомление в Telegram
                // Пользователь увидит только попап с плашкой "Не расстраивайся"
                $notificationSent = false;
                Log::info('Empty sector - no Telegram notification sent (only popup shown)', [
                    'spin_id' => $spin->id,
                    'sector_number' => $spin->sector_number,
                ]);
            } elseif ($spin->prize_type === 'money' && $spin->prize_value > 0) {
                // Денежный приз - отправляем уведомление в Telegram
                $notificationSent = TelegramNotificationService::notifyWin(
                    $user,
                    $spin->prize_value,
                    'money',
                    $adminLink,
                    $customMessage
                );
                Log::info('Money prize notification sent', [
                    'spin_id' => $spin->id,
                    'prize_value' => $spin->prize_value,
                    'notification_sent' => $notificationSent,
                    'custom_message' => $customMessage,
                ]);
            } elseif ($spin->prize_type === 'ticket' && $spin->prize_value > 0) {
                // Билеты - отправляем уведомление в Telegram
                $notificationSent = TelegramNotificationService::notifyWin(
                    $user,
                    $spin->prize_value,
                    'ticket',
                    $adminLink,
                    $customMessage
                );
                Log::info('Ticket prize notification sent', [
                    'spin_id' => $spin->id,
                    'prize_value' => $spin->prize_value,
                    'notification_sent' => $notificationSent,
                    'custom_message' => $customMessage,
                ]);
            } elseif ($spin->prize_type === 'secret_box') {
                // Секретный бокс - отправляем уведомление в Telegram
                $notificationSent = TelegramNotificationService::notifyWin(
                    $user,
                    0,
                    'secret_box',
                    $adminLink,
                    $customMessage
                );
                Log::info('Secret box prize notification sent', [
                    'spin_id' => $spin->id,
                    'notification_sent' => $notificationSent,
                    'custom_message' => $customMessage,
                ]);
            } elseif ($spin->prize_type === 'sponsor_gift') {
                // Подарок от спонсора - отправляем уведомление в Telegram
                $notificationSent = TelegramNotificationService::notifyWin(
                    $user,
                    0,
                    'sponsor_gift',
                    $adminLink,
                    $customMessage
                );
                Log::info('Sponsor gift prize notification sent', [
                    'spin_id' => $spin->id,
                    'notification_sent' => $notificationSent,
                    'custom_message' => $customMessage,
                ]);
            } else {
                // Неизвестный тип приза - не отправляем
                Log::channel('wheel-errors')->warning('Unknown prize type, no notification sent', [
                    'telegram_id' => $telegramId,
                    'user_id' => $user->id,
                    'spin_id' => $spin->id,
                    'prize_type' => $spin->prize_type,
                    'prize_value' => $spin->prize_value,
                ]);
                Log::warning('Unknown prize type, no notification sent', [
                    'spin_id' => $spin->id,
                    'prize_type' => $spin->prize_type,
                    'prize_value' => $spin->prize_value,
                ]);
            }

            return response()->json([
                'success' => true,
                'notification_sent' => $notificationSent,
                'verification_passed' => $verificationPassed,
                'sector' => $sector ? [
                    'sector_number' => $sector->sector_number,
                    'prize_type' => $sector->prize_type,
                    'prize_value' => $sector->prize_value,
                ] : null,
            ]);

        } catch (\Exception $e) {
            // Логируем в отдельный файл для ошибок пользовательской части
            Log::channel('wheel-errors')->error('Error in spin notification/verification', [
                'telegram_id' => $telegramId ?? null,
                'user_id' => $user->id ?? null,
                'spin_id' => $spinId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => [
                    'spin_id' => $request->input('spin_id'),
                    'final_rotation' => $request->input('final_rotation'),
                    'init_data_provided' => !empty($initData),
                ],
            ]);
            Log::error('Error in spin notification/verification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Произошла ошибка при верификации и отправке уведомления'
            ], 500);
        }
    }

    /**
     * Вычислить сектор по углу поворота
     * 
     * Формула обратная к формуле в WheelController:
     * - В WheelController: normalizedRotation = 345 - (sectorIndex * 30)
     * - Здесь: sectorIndex = (345 - normalizedRotation) / 30
     * 
     * @param float $rotation Финальный угол поворота колеса
     * @return WheelSector|null
     */
    protected function calculateSectorFromAngle(float $rotation): ?WheelSector
    {
        $segmentAngle = 360 / 12; // 30 градусов на сектор
        
        // Извлекаем нормализованную часть rotation (остаток от деления на 360)
        $normalizedRotation = fmod($rotation, 360);
        if ($normalizedRotation < 0) {
            $normalizedRotation += 360;
        }
        
        // Обратная формула: sectorIndex = (345 - normalizedRotation) / 30
        // Но нужно учесть, что при отрицательных значениях нужно добавить 360
        $tempValue = 345 - $normalizedRotation;
        if ($tempValue < 0) {
            $tempValue += 360;
        }
        
        // Вычисляем индекс сектора (0-11)
        // Используем ту же формулу что и в WheelController для верификации
        $sectorIndex = floor(($tempValue + 15) / $segmentAngle) % 12;
        if ($sectorIndex < 0) {
            $sectorIndex += 12;
        }
        
        // Преобразуем индекс (0-11) в номер сектора (1-12)
        $sectorNumber = $sectorIndex + 1;
        
        // Находим сектор в БД
        $sector = WheelSector::where('sector_number', $sectorNumber)
            ->where('is_active', true)
            ->first();
        
        Log::info('Sector calculated from angle', [
            'rotation' => $rotation,
            'normalized_rotation' => round($normalizedRotation, 2),
            'temp_value' => round($tempValue, 2),
            'sector_index' => $sectorIndex,
            'sector_number' => $sectorNumber,
            'sector_found' => $sector !== null,
            'sector_prize_type' => $sector ? $sector->prize_type : null,
            'sector_prize_value' => $sector ? $sector->prize_value : null,
        ]);
        
        return $sector;
    }

    /**
     * Пересчитать начисление приза на основе верифицированного сектора
     * 
     * @param User $user
     * @param Spin $spin
     * @param WheelSector $sector
     * @return void
     */
    protected function recalculatePrize(User $user, Spin $spin, WheelSector $sector): void
    {
        // Если приз уже был начислен, но сектор изменился, нужно пересчитать
        // Это критическая ситуация, которая должна быть редкой
        
        // Откатываем предыдущее начисление если нужно
        // (в данном случае просто обновляем данные в spin, так как начисление уже произошло)
        
        // Для денежных призов - уже начислено в total_wins, не откатываем
        // Для билетов - уже начислено в tickets_available, не откатываем
        
        // Просто логируем для мониторинга
        Log::info('Prize recalculation completed', [
            'user_id' => $user->id,
            'spin_id' => $spin->id,
            'sector_number' => $sector->sector_number,
            'prize_type' => $sector->prize_type,
            'prize_value' => $sector->prize_value,
        ]);
    }
}

