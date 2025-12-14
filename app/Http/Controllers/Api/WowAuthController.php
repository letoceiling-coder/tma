<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserTicket;
use App\Models\WheelSetting;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WowAuthController extends Controller
{
    /**
     * Инициализация/регистрация пользователя WOW при первом запуске Mini App
     * Создает или обновляет пользователя на основе данных из Telegram
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function init(Request $request): JsonResponse
    {
        try {
            $initData = $request->header('X-Telegram-Init-Data') ?? $request->query('initData');
            
            if (!$initData) {
                return response()->json([
                    'error' => 'Init data not provided'
                ], 401);
            }

            // Валидация initData (пропускаем в режиме разработки)
            $botToken = config('services.telegram.bot_token');
            $isDebug = config('app.debug');
            
            if ($botToken && !TelegramService::validateInitData($initData, $botToken)) {
                if (!$isDebug) {
                    Log::warning('Invalid initData signature', [
                        'initData' => substr($initData, 0, 50) . '...',
                    ]);
                    return response()->json([
                        'error' => 'Invalid init data'
                    ], 401);
                }
                
                // В режиме разработки разрешаем запрос с невалидным initData
                Log::info('Development mode: skipping initData validation');
            }

            // Парсим данные пользователя из initData
            $userData = TelegramService::parseInitData($initData);
            
            if (!isset($userData['user']['id'])) {
                return response()->json([
                    'error' => 'User ID not found in init data'
                ], 401);
            }

            $telegramId = $userData['user']['id'];
            $telegramUser = $userData['user'];

            DB::beginTransaction();

            try {
                // Получаем настройки для определения количества стартовых билетов
                $settings = WheelSetting::getSettings();
                $initialTicketsCount = $settings->getValidStartTickets(); // Валидированное значение (по умолчанию 1)

                // Находим или создаем пользователя
                $user = User::firstOrCreate(
                    ['telegram_id' => $telegramId],
                    [
                        'name' => $telegramUser['first_name'] ?? 'Telegram User',
                        'email' => "telegram_{$telegramId}@telegram.local",
                        'password' => bcrypt(str()->random(32)),
                        'username' => $telegramUser['username'] ?? null,
                        'avatar_url' => $telegramUser['photo_url'] ?? null,
                        'tickets_available' => $initialTicketsCount, // Используем настройку из админки
                        'stars_balance' => 0,
                        'total_spins' => 0,
                        'total_wins' => 0,
                    ]
                );

                $isNewUser = $user->wasRecentlyCreated;

                // Обновляем данные пользователя из Telegram (на случай изменения имени/аватара)
                $updateData = [];
                
                if (isset($telegramUser['first_name'])) {
                    $fullName = $telegramUser['first_name'];
                    if (isset($telegramUser['last_name'])) {
                        $fullName .= ' ' . $telegramUser['last_name'];
                    }
                    $updateData['name'] = $fullName;
                }
                
                if (isset($telegramUser['username'])) {
                    $updateData['username'] = $telegramUser['username'];
                }
                
                if (isset($telegramUser['photo_url'])) {
                    $updateData['avatar_url'] = $telegramUser['photo_url'];
                }

                // Если это новый пользователь, начисляем стартовые билеты (только один раз при первом входе)
                if ($isNewUser) {
                    $updateData['tickets_available'] = $initialTicketsCount;
                    
                    // Создаем запись в user_tickets для отслеживания источника
                    UserTicket::create([
                        'user_id' => $user->id,
                        'tickets_count' => $initialTicketsCount,
                        'restored_at' => null, // Стартовые билеты доступны сразу
                        'source' => 'initial_bonus',
                    ]);
                    
                    Log::info('Initial tickets granted to new user', [
                        'user_id' => $user->id,
                        'telegram_id' => $telegramId,
                        'initial_tickets_count' => $initialTicketsCount,
                    ]);
                }

                if (!empty($updateData)) {
                    $user->update($updateData);
                }

                DB::commit();

                Log::info('WOW User initialized', [
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                    'is_new' => $isNewUser,
                ]);

                return response()->json([
                    'success' => true,
                    'user' => [
                        'id' => $user->id,
                        'telegram_id' => $user->telegram_id,
                        'name' => $user->name,
                        'username' => $user->username,
                        'avatar_url' => $user->avatar_url,
                        'tickets_available' => $user->tickets_available,
                        'stars_balance' => $user->stars_balance,
                        'total_spins' => $user->total_spins,
                        'total_wins' => $user->total_wins,
                    ],
                    'is_new_user' => $isNewUser,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error initializing WOW user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Произошла ошибка при инициализации пользователя'
            ], 500);
        }
    }
}

