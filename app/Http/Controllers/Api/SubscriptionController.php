<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    /**
     * Получить список активных каналов для проверки подписки
     * 
     * @return JsonResponse
     */
    public function getActiveChannels(): JsonResponse
    {
        $channels = \App\Models\Channel::getActiveChannels();
        
        return response()->json([
            'channels' => $channels->map(function ($channel) {
                return [
                    'username' => $channel->username,
                    'title' => $channel->title,
                    'priority' => $channel->priority,
                ];
            }),
        ]);
    }
    
    /**
     * Проверка подписки пользователя на канал Telegram
     * 
     * @param Request $request
     * @param string $channelUsername
     * @return JsonResponse
     */
    public function checkSubscription(Request $request, string $channelUsername): JsonResponse
    {
        try {
            // Получаем initData из заголовка
            $initData = $request->header('X-Telegram-Init-Data');
            
            if (!$initData) {
                // Если нет initData, проверяем через query параметр (для тестирования)
                $initData = $request->query('initData');
            }

            // Если нет initData, возвращаем false (для разработки можно вернуть true)
            if (!$initData) {
                return response()->json([
                    'is_subscribed' => false,
                    'message' => 'Init data not provided'
                ]);
            }

            // Парсим initData для получения user_id
            $userData = $this->parseInitData($initData);
            
            if (!isset($userData['user']['id'])) {
                return response()->json([
                    'is_subscribed' => false,
                    'message' => 'User ID not found in init data'
                ]);
            }

            $userId = $userData['user']['id'];
            $botToken = config('services.telegram.bot_token');

            // Если токен бота не настроен, блокируем доступ
            if (!$botToken) {
                Log::error('Telegram bot token not configured - проверка подписки невозможна');
                return response()->json([
                    'is_subscribed' => false, // Блокируем доступ если токен не настроен
                    'message' => 'Bot token not configured'
                ]);
            }

            // Проверяем подписку через Telegram Bot API
            $chatId = '@' . ltrim($channelUsername, '@');
            
            Log::info('Checking subscription', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'channel' => $channelUsername,
            ]);
            
            $response = Http::get("https://api.telegram.org/bot{$botToken}/getChatMember", [
                'chat_id' => $chatId,
                'user_id' => $userId,
            ]);

            if ($response->failed()) {
                $errorBody = $response->body();
                $errorJson = json_decode($errorBody, true);
                
                Log::error('Telegram API error', [
                    'status' => $response->status(),
                    'body' => $errorBody,
                    'error_code' => $errorJson['error_code'] ?? null,
                    'description' => $errorJson['description'] ?? null,
                    'channel' => $channelUsername,
                    'user_id' => $userId,
                ]);
                
                // Если ошибка "user not found" или "bot is not a member" - пользователь точно не подписан
                // Если ошибка "bot is not an admin" - нужно сделать бота админом
                $errorDescription = $errorJson['description'] ?? '';
                if (str_contains($errorDescription, 'bot is not an admin')) {
                    return response()->json([
                        'is_subscribed' => false,
                        'message' => 'Bot must be admin of the channel',
                        'debug' => [
                            'error' => $errorDescription,
                            'chat_id' => $chatId,
                        ]
                    ]);
                }
                
                // При ошибке API блокируем доступ - считаем что не подписан
                return response()->json([
                    'is_subscribed' => false,
                    'message' => 'API error, subscription check failed',
                    'debug' => [
                        'error' => $errorDescription,
                        'status' => $response->status(),
                    ]
                ]);
            }

            $result = $response->json();
            
            Log::info('Telegram API response', [
                'ok' => $result['ok'] ?? false,
                'result' => $result['result'] ?? null,
                'channel' => $channelUsername,
            ]);
            
            if (!isset($result['ok']) || !$result['ok']) {
                Log::warning('Telegram API returned not ok', [
                    'result' => $result,
                    'channel' => $channelUsername,
                    'user_id' => $userId,
                ]);
                
                return response()->json([
                    'is_subscribed' => false,
                    'message' => $result['description'] ?? 'Unknown error',
                    'debug' => [
                        'result' => $result,
                    ]
                ]);
            }

            $status = $result['result']['status'] ?? null;
            
            // Пользователь считается подписанным если статус: member, administrator, creator
            // Статусы: left, kicked - не подписан
            // Статусы: member, administrator, creator, restricted - подписан
            $isSubscribed = in_array($status, ['member', 'administrator', 'creator', 'restricted']);

            Log::info('Subscription check result', [
                'channel' => $channelUsername,
                'user_id' => $userId,
                'status' => $status,
                'is_subscribed' => $isSubscribed,
            ]);

            return response()->json([
                'is_subscribed' => $isSubscribed,
                'status' => $status,
                'debug' => config('app.debug') ? [
                    'chat_id' => $chatId,
                    'user_id' => $userId,
                    'result_status' => $status,
                ] : null,
            ]);

        } catch (\Exception $e) {
            Log::error('Subscription check error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'channel' => $channelUsername,
            ]);

            // При ошибке блокируем доступ - считаем что не подписан
            return response()->json([
                'is_subscribed' => false,
                'message' => 'Error occurred during subscription check'
            ]);
        }
    }

    /**
     * Проверка подписки на все обязательные каналы
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function checkAllSubscriptions(Request $request): JsonResponse
    {
        try {
            $initData = $request->header('X-Telegram-Init-Data') ?? $request->query('initData');
            
            if (!$initData) {
                return response()->json([
                    'all_subscribed' => false,
                    'channels' => [],
                    'message' => 'Init data not provided'
                ]);
            }

            $userData = \App\Services\TelegramService::parseInitData($initData);
            
            if (!isset($userData['user']['id'])) {
                return response()->json([
                    'all_subscribed' => false,
                    'channels' => [],
                    'message' => 'User ID not found in init data'
                ]);
            }

            $userId = $userData['user']['id'];
            $channels = \App\Models\Channel::getActiveChannels();
            
            $results = [];
            $allSubscribed = true;
            $botToken = config('services.telegram.bot_token');

            foreach ($channels as $channel) {
                $chatId = '@' . ltrim($channel->username, '@');
                
                try {
                    $response = Http::get("https://api.telegram.org/bot{$botToken}/getChatMember", [
                        'chat_id' => $chatId,
                        'user_id' => $userId,
                    ]);

                    if ($response->successful()) {
                        $result = $response->json();
                        $status = $result['result']['status'] ?? null;
                        $isSubscribed = in_array($status, ['member', 'administrator', 'creator', 'restricted']);
                        
                        $results[] = [
                            'username' => $channel->username,
                            'title' => $channel->title,
                            'is_subscribed' => $isSubscribed,
                            'status' => $status,
                        ];
                        
                        if (!$isSubscribed) {
                            $allSubscribed = false;
                        }
                    } else {
                        $results[] = [
                            'username' => $channel->username,
                            'title' => $channel->title,
                            'is_subscribed' => false,
                            'status' => null,
                        ];
                        $allSubscribed = false;
                    }
                } catch (\Exception $e) {
                    Log::error('Error checking subscription for channel', [
                        'channel' => $channel->username,
                        'error' => $e->getMessage(),
                    ]);
                    $results[] = [
                        'username' => $channel->username,
                        'title' => $channel->title,
                        'is_subscribed' => false,
                        'status' => null,
                    ];
                    $allSubscribed = false;
                }
            }

            return response()->json([
                'all_subscribed' => $allSubscribed,
                'channels' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in checkAllSubscriptions', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'all_subscribed' => false,
                'channels' => [],
                'message' => 'Error occurred during subscription check'
            ]);
        }
    }

    /**
     * Парсинг initData от Telegram WebApp
     * 
     * @param string $initData
     * @return array
     */
    private function parseInitData(string $initData): array
    {
        $data = \App\Services\TelegramService::parseInitData($initData) ?? [];
        return $data;
    }
}

