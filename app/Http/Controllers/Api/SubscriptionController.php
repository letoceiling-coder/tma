<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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
            // Исправляем путь к токену - используем config('telegram.bot_token') вместо config('services.telegram.bot_token')
            $botToken = config('telegram.bot_token') ?? config('services.telegram.bot_token');

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
            
            // Кешируем результат проверки: положительные результаты на 5 минут, отрицательные на 30 секунд
            $cacheKey = "subscription_check_{$userId}_{$channelUsername}";
            $cachedResult = Cache::get($cacheKey);
            
            if ($cachedResult !== null) {
                Log::info('Using cached subscription result', [
                    'channel' => $channelUsername,
                    'user_id' => $userId,
                    'is_subscribed' => $cachedResult,
                ]);
                return response()->json([
                    'is_subscribed' => $cachedResult,
                    'status' => 'cached',
                ]);
            }
            
            Log::info('Checking subscription', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'channel' => $channelUsername,
            ]);
            
            // Добавляем retry для временных ошибок API
            // Для 429 (rate limit) делаем больше попыток с большей задержкой
            $maxRetries = 3;
            $retryDelay = 1000; // миллисекунды
            $response = null;
            $lastErrorStatus = null;
            
            for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
                if ($attempt > 0) {
                    // Для 429 увеличиваем задержку экспоненциально
                    $delay = $lastErrorStatus === 429 
                        ? $retryDelay * pow(2, $attempt - 1) 
                        : $retryDelay * $attempt;
                    usleep($delay * 1000);
                }
                
                $response = Http::timeout(10)->get("https://api.telegram.org/bot{$botToken}/getChatMember", [
                    'chat_id' => $chatId,
                    'user_id' => $userId,
                ]);
                
                // Если запрос успешен, выходим
                if ($response->successful()) {
                    break;
                }
                
                $lastErrorStatus = $response->status();
                
                // Если ошибка не временная, выходим из цикла
                if ($lastErrorStatus !== 429 && $lastErrorStatus !== 500 && $lastErrorStatus !== 502 && $lastErrorStatus !== 503) {
                    break;
                }
                
                if ($attempt < $maxRetries) {
                    Log::warning('Retrying subscription check', [
                        'attempt' => $attempt + 1,
                        'status' => $lastErrorStatus,
                        'channel' => $channelUsername,
                    ]);
                }
            }

            if ($response->failed()) {
                $errorBody = $response->body();
                $errorJson = json_decode($errorBody, true);
                $errorDescription = $errorJson['description'] ?? '';
                
                Log::error('Telegram API error', [
                    'status' => $response->status(),
                    'body' => $errorBody,
                    'error_code' => $errorJson['error_code'] ?? null,
                    'description' => $errorDescription,
                    'channel' => $channelUsername,
                    'user_id' => $userId,
                ]);
                
                // Если ошибка "user not found" или "chat not found" - пользователь точно не подписан
                if (str_contains($errorDescription, 'user not found') || 
                    str_contains($errorDescription, 'chat not found') ||
                    str_contains($errorDescription, 'user is not a member')) {
                    // Кешируем отрицательный результат на 30 секунд
                    Cache::put($cacheKey, false, 30);
                    return response()->json([
                        'is_subscribed' => false,
                        'message' => 'User not found or not a member',
                    ]);
                }
                
                // Если ошибка "bot is not an admin" - нужно сделать бота админом
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
                
                // Для временных ошибок (429, 500, 502, 503) после всех попыток
                // Проверяем, есть ли кешированный положительный результат (даже если он истек)
                // Если есть, используем его, чтобы не блокировать пользователя
                $cacheKeyPositive = "subscription_check_positive_{$userId}_{$channelUsername}";
                $cachedPositive = Cache::get($cacheKeyPositive);
                
                if ($cachedPositive === true) {
                    Log::warning('Using cached positive result after API failure', [
                        'channel' => $channelUsername,
                        'user_id' => $userId,
                        'api_error' => $errorDescription,
                        'api_status' => $response->status(),
                    ]);
                    // Обновляем основной кеш
                    Cache::put($cacheKey, true, 60); // Кешируем на 1 минуту
                    return response()->json([
                        'is_subscribed' => true,
                        'status' => 'cached_after_error',
                        'message' => 'Using cached result due to API error',
                    ]);
                }
                
                // Если нет кешированного положительного результата, блокируем доступ
                Log::warning('No cached positive result, blocking access', [
                    'channel' => $channelUsername,
                    'user_id' => $userId,
                    'api_error' => $errorDescription,
                    'api_status' => $response->status(),
                ]);
                
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

            // Кешируем результат: положительные на 5 минут, отрицательные на 30 секунд
            $cacheTime = $isSubscribed ? 300 : 30; // 5 минут для подписанных, 30 секунд для неподписанных
            Cache::put($cacheKey, $isSubscribed, $cacheTime);
            
            // Дополнительно кешируем положительные результаты отдельно для использования при ошибках API
            if ($isSubscribed) {
                Cache::put("subscription_check_positive_{$userId}_{$channelUsername}", true, 600); // 10 минут
            }

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
            // Исправляем путь к токену
            $botToken = config('telegram.bot_token') ?? config('services.telegram.bot_token');

            foreach ($channels as $channel) {
                $chatId = '@' . ltrim($channel->username, '@');
                
                // Проверяем кеш
                $cacheKey = "subscription_check_{$userId}_{$channel->username}";
                $cachedResult = Cache::get($cacheKey);
                
                if ($cachedResult !== null) {
                    $results[] = [
                        'username' => $channel->username,
                        'title' => $channel->title,
                        'is_subscribed' => $cachedResult,
                        'status' => 'cached',
                    ];
                    if (!$cachedResult) {
                        $allSubscribed = false;
                    }
                    continue;
                }
                
                try {
                    // Retry логика для временных ошибок
                    $maxRetries = 3;
                    $retryDelay = 1000;
                    $response = null;
                    $lastErrorStatus = null;
                    
                    for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
                        if ($attempt > 0) {
                            $delay = $lastErrorStatus === 429 
                                ? $retryDelay * pow(2, $attempt - 1) 
                                : $retryDelay * $attempt;
                            usleep($delay * 1000);
                        }
                        
                        $response = Http::timeout(10)->get("https://api.telegram.org/bot{$botToken}/getChatMember", [
                            'chat_id' => $chatId,
                            'user_id' => $userId,
                        ]);
                        
                        if ($response->successful()) {
                            break;
                        }
                        
                        $lastErrorStatus = $response->status();
                        
                        if ($lastErrorStatus !== 429 && $lastErrorStatus !== 500 && $lastErrorStatus !== 502 && $lastErrorStatus !== 503) {
                            break;
                        }
                    }

                    if ($response->successful()) {
                        $result = $response->json();
                        $status = $result['result']['status'] ?? null;
                        $isSubscribed = in_array($status, ['member', 'administrator', 'creator', 'restricted']);
                        
                        // Кешируем результат: положительные на 5 минут, отрицательные на 30 секунд
                        $cacheTime = $isSubscribed ? 300 : 30;
                        Cache::put($cacheKey, $isSubscribed, $cacheTime);
                        
                        if ($isSubscribed) {
                            Cache::put("subscription_check_positive_{$userId}_{$channel->username}", true, 600);
                        }
                        
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
                        // При ошибке проверяем кеш положительных результатов
                        $cacheKeyPositive = "subscription_check_positive_{$userId}_{$channel->username}";
                        $cachedPositive = Cache::get($cacheKeyPositive);
                        
                        if ($cachedPositive === true) {
                            $results[] = [
                                'username' => $channel->username,
                                'title' => $channel->title,
                                'is_subscribed' => true,
                                'status' => 'cached_after_error',
                            ];
                        } else {
                            $results[] = [
                                'username' => $channel->username,
                                'title' => $channel->title,
                                'is_subscribed' => false,
                                'status' => null,
                            ];
                            $allSubscribed = false;
                        }
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

