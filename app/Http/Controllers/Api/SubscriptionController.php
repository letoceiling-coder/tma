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
            $response = Http::get("https://api.telegram.org/bot{$botToken}/getChatMember", [
                'chat_id' => $chatId,
                'user_id' => $userId,
            ]);

            if ($response->failed()) {
                Log::error('Telegram API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'channel' => $channelUsername,
                    'user_id' => $userId,
                ]);
                
                // При ошибке API блокируем доступ - считаем что не подписан
                return response()->json([
                    'is_subscribed' => false,
                    'message' => 'API error, subscription check failed'
                ]);
            }

            $result = $response->json();
            
            if (!isset($result['ok']) || !$result['ok']) {
                return response()->json([
                    'is_subscribed' => false,
                    'message' => $result['description'] ?? 'Unknown error'
                ]);
            }

            $status = $result['result']['status'] ?? null;
            
            // Пользователь считается подписанным если статус: member, administrator, creator
            $isSubscribed = in_array($status, ['member', 'administrator', 'creator']);

            return response()->json([
                'is_subscribed' => $isSubscribed,
                'status' => $status,
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
     * Парсинг initData от Telegram WebApp
     * 
     * @param string $initData
     * @return array
     */
    private function parseInitData(string $initData): array
    {
        $data = [];
        parse_str($initData, $data);

        // Декодируем user данные если они есть
        if (isset($data['user'])) {
            $data['user'] = json_decode($data['user'], true);
        }

        return $data;
    }
}

