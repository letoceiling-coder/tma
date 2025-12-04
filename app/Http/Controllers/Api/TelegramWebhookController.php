<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Telegram\Bot;
use App\Telegram\Keyboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    /**
     * Обработка webhook от Telegram
     */
    public function handle(Request $request)
    {
        $update = $request->all();
        
        Log::info('Telegram webhook received', ['update' => $update]);

        // Обработка сообщений
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }

        // Обработка callback query
        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Обработка сообщений
     */
    protected function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? '';

        if (!$chatId) {
            return;
        }

        // Обработка команды /start
        if ($text === '/start' || str_starts_with($text, '/start ')) {
            $this->handleStartCommand($chatId, $message);
        }
    }

    /**
     * Обработка команды /start
     */
    protected function handleStartCommand(int|string $chatId, array $message): void
    {
        $config = config('telegram.welcome_message');

        // Проверяем, включено ли приветственное сообщение
        if (!($config['enabled'] ?? true)) {
            return;
        }

        $welcomeText = $config['text'] ?? '<b>Добро пожаловать!</b>';
        $miniAppButton = $config['mini_app_button'] ?? [];

        try {
            $bot = new Bot();
            
            $params = [
                'parse_mode' => 'HTML',
            ];

            // Добавляем кнопку Mini App, если включена
            if (!empty($miniAppButton['enabled']) && !empty($miniAppButton['url'])) {
                $keyboard = Keyboard::inline()
                    ->row()
                    ->webApp(
                        $miniAppButton['text'] ?? '🚀 Открыть приложение',
                        $miniAppButton['url']
                    )
                    ->toArray();

                $params['reply_markup'] = json_encode($keyboard);
            }

            $bot->sendMessage($chatId, $welcomeText, $params);

            Log::info('Welcome message sent', [
                'chat_id' => $chatId,
                'text' => $welcomeText,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send welcome message', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Обработка callback query
     */
    protected function handleCallback(array $callback): void
    {
        $queryId = $callback['id'] ?? null;
        $data = $callback['data'] ?? '';

        if (!$queryId) {
            return;
        }

        try {
            $callbackHandler = app('telegram.callback');
            $callbackHandler->acknowledge($queryId);

            // Дополнительная обработка callback, если нужно
            Log::info('Callback query processed', [
                'query_id' => $queryId,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process callback query', [
                'query_id' => $queryId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

