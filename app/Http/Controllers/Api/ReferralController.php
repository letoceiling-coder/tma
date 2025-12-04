<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Referral;
use App\Services\TelegramService;
use App\Telegram\Bot;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ReferralController extends Controller
{
    /**
     * Получить реферальную ссылку пользователя
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getLink(Request $request): JsonResponse
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

            $user = User::where('telegram_id', $telegramId)->first();
            
            if (!$user) {
                return response()->json([
                    'error' => 'User not found'
                ], 404);
            }

            // Генерируем реферальную ссылку для Telegram бота
            // Формат: https://t.me/{bot_username}?start=ref{telegram_id}
            $botUsername = config('telegram.bot_username');
            
            // Если username не задан в конфигурации, получаем через API
            if (!$botUsername) {
                $botUsername = Cache::remember('telegram_bot_username', 3600, function () {
                    try {
                        $bot = new Bot();
                        $me = $bot->getMe();
                        return $me['username'] ?? null;
                    } catch (\Exception $e) {
                        Log::error('Failed to get bot username from API', [
                            'error' => $e->getMessage(),
                        ]);
                        return null;
                    }
                });
            }
            
            if (!$botUsername) {
                return response()->json([
                    'error' => 'Bot username not available. Please configure TELEGRAM_BOT_USERNAME in .env file.'
                ], 500);
            }
            
            // Убираем @ если оно есть
            $botUsername = ltrim($botUsername, '@');
            
            $referralLink = "https://t.me/{$botUsername}?start=ref{$user->telegram_id}";

            return response()->json([
                'referral_link' => $referralLink,
                'telegram_id' => $user->telegram_id,
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting referral link', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Регистрация по реферальной ссылке
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $initData = $request->header('X-Telegram-Init-Data') ?? $request->query('initData');
            $referrerId = $request->input('referrer_id'); // telegram_id пригласившего
            
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

            if (!$referrerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Referrer ID not provided'
                ]);
            }

            DB::beginTransaction();

            try {
                // Находим или создаем приглашенного пользователя
                $user = User::firstOrCreate(
                    ['telegram_id' => $telegramId],
                    [
                        'name' => 'Telegram User',
                        'email' => "telegram_{$telegramId}@telegram.local",
                        'password' => bcrypt(str()->random(32)),
                    ]
                );

                // Находим пригласившего
                $referrer = User::where('telegram_id', $referrerId)->first();

                if (!$referrer) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Referrer not found'
                    ], 404);
                }

                // Проверяем, что пользователь не приглашает сам себя
                if ($user->id === $referrer->id) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot refer yourself'
                    ]);
                }

                // Проверяем, что эта связь еще не существует
                $existingReferral = Referral::where('inviter_id', $referrer->id)
                    ->where('invited_id', $user->id)
                    ->first();

                if ($existingReferral) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Referral already exists'
                    ]);
                }

                // Создаем реферальную связь
                Referral::create([
                    'inviter_id' => $referrer->id,
                    'invited_id' => $user->id,
                    'invited_at' => now(),
                ]);

                // Обновляем invited_by у пользователя
                if (!$user->invited_by) {
                    $user->invited_by = $referrer->id;
                    $user->save();
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Referral registered successfully',
                    'referrer_id' => $referrer->telegram_id,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error registering referral', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Произошла ошибка при регистрации реферала'
            ], 500);
        }
    }

    /**
     * Получить статистику рефералов пользователя
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $initData = $request->header('X-Telegram-Init-Data') ?? $request->query('initData');
            
            if (!$initData) {
                return response()->json([
                    'total_invites' => 0,
                    'current_month_invites' => 0,
                ]);
            }

            $telegramId = TelegramService::getTelegramId($initData);
            
            if (!$telegramId) {
                return response()->json([
                    'total_invites' => 0,
                    'current_month_invites' => 0,
                ]);
            }

            $user = User::where('telegram_id', $telegramId)->first();
            
            if (!$user) {
                return response()->json([
                    'total_invites' => 0,
                    'current_month_invites' => 0,
                ]);
            }

            $totalInvites = Referral::where('inviter_id', $user->id)->count();
            $currentMonthInvites = Referral::getInvitesCountForUser(
                $user->id, 
                now()->month, 
                now()->year
            );

            return response()->json([
                'total_invites' => $totalInvites,
                'current_month_invites' => $currentMonthInvites,
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting referral stats', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'total_invites' => 0,
                'current_month_invites' => 0,
            ]);
        }
    }
}

