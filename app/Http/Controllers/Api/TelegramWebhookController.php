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
        
        Log::info('Telegram webhook received', [
            'has_message' => isset($update['message']),
            'has_callback' => isset($update['callback_query']),
            'update_id' => $update['update_id'] ?? null,
        ]);

        // Обработка сообщений
        if (isset($update['message'])) {
            Log::info('Processing message', [
                'chat_id' => $update['message']['chat']['id'] ?? null,
                'text' => $update['message']['text'] ?? null,
            ]);
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

        Log::info('handleMessage called', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);

        if (!$chatId) {
            Log::warning('No chat_id in message', ['message' => $message]);
            return;
        }

        // Обработка команды /start
        if ($text === '/start' || str_starts_with($text, '/start ')) {
            Log::info('Start command detected', ['chat_id' => $chatId]);
            $this->handleStartCommand($chatId, $message);
        } else {
            Log::info('Message is not /start command', ['text' => $text]);
        }
    }

    /**
     * Обработка команды /start
     */
    protected function handleStartCommand(int|string $chatId, array $message): void
    {
        Log::info('handleStartCommand called', ['chat_id' => $chatId]);
        
        $config = config('telegram.welcome_message');
        
        Log::info('Welcome message config', [
            'config' => $config,
            'enabled' => $config['enabled'] ?? true,
        ]);

        // Проверяем, включено ли приветственное сообщение
        if (!($config['enabled'] ?? true)) {
            Log::info('Welcome message is disabled');
            return;
        }

        $welcomeText = $config['text'] ?? '<b>Добро пожаловать!</b>';
        $miniAppButton = $config['mini_app_button'] ?? [];

        Log::info('Preparing to send message', [
            'chat_id' => $chatId,
            'welcome_text' => $welcomeText,
            'mini_app_button' => $miniAppButton,
        ]);

        try {
            // Проверяем наличие токена
            $token = config('telegram.bot_token');
            if (!$token) {
                Log::error('Bot token is not configured');
                return;
            }
            
            $bot = new Bot();
            
            $params = [
                'parse_mode' => 'HTML',
            ];

            // Добавляем кнопку Mini App, если включена
            if (!empty($miniAppButton['enabled'])) {
                // Используем URL из настроек кнопки или из общих настроек
                $buttonUrl = $miniAppButton['url'] ?? config('telegram.mini_app_url');
                
                Log::info('Mini App button enabled', [
                    'button_url' => $buttonUrl,
                    'button_text' => $miniAppButton['text'] ?? '🚀 Открыть приложение',
                ]);
                
                if (!empty($buttonUrl)) {
                    $keyboard = Keyboard::inline()
                        ->webApp(
                            $miniAppButton['text'] ?? '🚀 Открыть приложение',
                            $buttonUrl
                        )
                        ->get();

                    $params['reply_markup'] = json_encode($keyboard);
                    
                    Log::info('Keyboard created', ['keyboard' => $params['reply_markup']]);
                } else {
                    Log::warning('Mini App button enabled but URL is empty');
                }
            }

            Log::info('Sending message', [
                'chat_id' => $chatId,
                'params' => $params,
            ]);

            $result = $bot->sendMessage($chatId, $welcomeText, $params);

            Log::info('Welcome message sent successfully', [
                'chat_id' => $chatId,
                'result' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send welcome message', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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

