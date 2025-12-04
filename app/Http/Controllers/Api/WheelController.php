<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WheelSector;
use App\Models\WheelSetting;
use App\Models\Spin;
use App\Models\User;
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

            // Найти или создать пользователя
            $user = User::firstOrCreate(
                ['telegram_id' => $telegramId],
                [
                    'name' => 'Telegram User',
                    'email' => "telegram_{$telegramId}@telegram.local",
                    'password' => bcrypt(str()->random(32)),
                ]
            );

            // Проверка наличия билетов
            if ($user->tickets_available <= 0) {
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
                return response()->json([
                    'error' => 'No active sectors configured'
                ], 500);
            }

            // Создаем запись о прокруте
            DB::beginTransaction();
            
            try {
                // Уменьшаем количество билетов
                $user->tickets_available = max(0, $user->tickets_available - 1);
                $user->last_spin_at = now();
                $user->total_spins++;
                $user->save();

                // Сохраняем результат прокрута
                $spin = Spin::create([
                    'user_id' => $user->id,
                    'spin_time' => now(),
                    'prize_type' => $winningSector->prize_type,
                    'prize_value' => $winningSector->prize_value,
                    'sector_id' => $winningSector->id,
                ]);

                // Если выигрыш - начисляем приз
                $prizeAwarded = false;
                if ($winningSector->prize_type === 'money' && $winningSector->prize_value > 0) {
                    // Здесь должна быть логика начисления денег пользователю
                    // Пока просто отмечаем что приз выигран
                    $user->total_wins++;
                    $user->save();
                    $prizeAwarded = true;
                    
                    // ПРИМЕЧАНИЕ: Уведомления о выигрыше отправляются отдельным endpoint
                    // после завершения анимации на фронтенде (4 секунды)
                } elseif ($winningSector->prize_type === 'ticket') {
                    // Начисляем билет
                    $user->tickets_available++;
                    $user->save();
                    $prizeAwarded = true;
                } elseif ($winningSector->prize_type === 'secret_box') {
                    // Секретный бокс - обрабатывается отдельно
                    $prizeAwarded = true;
                }

                DB::commit();

                // Рассчитываем угол поворота для анимации
                // Формула на фронтенде: winningIndex = floor((360 - normalizedRotation + 15) / 30) % 12
                // Решаем обратную задачу: для выбранного sectorIndex находим normalizedRotation
                // 
                // winningIndex = floor((360 - normalizedRotation + 15) / 30) % 12
                // Для точного попадания в центр сектора:
                // winningIndex = (360 - normalizedRotation + 15) / 30
                // winningIndex * 30 = 360 - normalizedRotation + 15
                // normalizedRotation = 360 + 15 - winningIndex * 30
                // normalizedRotation = 375 - winningIndex * 30
                
                $segmentAngle = 360 / 12; // 30 градусов на сектор
                
                // Генерируем случайное количество оборотов (5-10) для каждого прокрута
                // Это гарантирует, что каждый прокрут уникален и rotation постоянно увеличивается
                $randomSpins = rand(5, 10);
                $baseRotation = 360 * $randomSpins;
                
                // Преобразуем sector_number (1-12) в индекс (0-11)
                $sectorIndex = $winningSector->sector_number - 1;
                
                // Применяем обратную формулу
                $normalizedRotation = 375 - ($sectorIndex * $segmentAngle);
                
                // Нормализуем к диапазону 0-360
                $normalizedRotation = fmod($normalizedRotation, 360);
                if ($normalizedRotation < 0) {
                    $normalizedRotation += 360;
                }
                
                // ВАЖНО: Возвращаем ДЕЛЬТУ (относительное значение), а не абсолютное
                // Фронтенд накапливает rotation: setRotation((prev) => prev + data.rotation)
                // Случайное количество оборотов гарантирует уникальность каждого прокрута
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
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error in wheel spin', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Произошла ошибка при прокруте рулетки'
            ], 500);
        }
    }
}

