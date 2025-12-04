<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Telegram\Bot;
use App\Telegram\Keyboard;
use App\Models\User;
use App\Models\Referral;
use App\Services\TelegramNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
            Log::info('Start command detected', ['chat_id' => $chatId, 'text' => $text]);
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
        
        // Извлекаем параметр из команды /start ref{TELEGRAM_ID}
        $text = $message['text'] ?? '';
        $referrerTelegramId = null;
        
        if (str_starts_with($text, '/start ref')) {
            // Извлекаем telegram_id реферера из команды /start ref{TELEGRAM_ID}
            $parts = explode(' ', $text);
            if (count($parts) >= 2 && str_starts_with($parts[1], 'ref')) {
                $refParam = substr($parts[1], 3); // Убираем префикс "ref"
                if (is_numeric($refParam)) {
                    $referrerTelegramId = (int) $refParam;
                    Log::info('Referral link detected', [
                        'chat_id' => $chatId,
                        'referrer_telegram_id' => $referrerTelegramId,
                    ]);
                    
                    // Обрабатываем реферальную регистрацию
                    $this->handleReferralRegistration($chatId, $referrerTelegramId);
                }
            }
        }
        
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
                // Используем URL из настроек кнопки, если он задан, иначе из общих настроек
                $buttonUrl = !empty($miniAppButton['url']) 
                    ? $miniAppButton['url'] 
                    : config('telegram.mini_app_url');
                
                // Если URL все еще пустой, пробуем использовать APP_URL
                if (empty($buttonUrl)) {
                    $buttonUrl = rtrim(config('app.url', ''), '/');
                }
                
                Log::info('Mini App button enabled', [
                    'button_url' => $buttonUrl,
                    'button_text' => $miniAppButton['text'] ?? '🚀 Открыть приложение',
                    'mini_app_url_config' => config('telegram.mini_app_url'),
                    'app_url' => config('app.url'),
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
                    Log::warning('Mini App button enabled but URL is empty - no URL available from config');
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

    /**
     * Обработка реферальной регистрации
     * 
     * @param int|string $chatId Telegram ID нового пользователя (тот, кто перешел по ссылке)
     * @param int $referrerTelegramId Telegram ID реферера (тот, кто поделился ссылкой)
     * @return void
     */
    protected function handleReferralRegistration(int|string $chatId, int $referrerTelegramId): void
    {
        try {
            DB::beginTransaction();

            // Находим реферера по telegram_id
            $referrer = User::where('telegram_id', $referrerTelegramId)->first();
            
            if (!$referrer) {
                Log::warning('Referrer not found', [
                    'referrer_telegram_id' => $referrerTelegramId,
                    'chat_id' => $chatId,
                ]);
                DB::rollBack();
                return;
            }

            // Проверяем, что пользователь не приглашает сам себя
            if ($chatId == $referrerTelegramId) {
                Log::info('User tried to refer themselves', [
                    'telegram_id' => $chatId,
                ]);
                DB::rollBack();
                return;
            }

            // Проверяем, что пользователь еще не существует
            // Если пользователь уже существует, реферальная связь не создается
            $existingUser = User::where('telegram_id', $chatId)->first();
            if ($existingUser) {
                Log::info('User already exists, skipping referral registration', [
                    'user_id' => $existingUser->id,
                    'telegram_id' => $chatId,
                    'referrer_telegram_id' => $referrerTelegramId,
                ]);
                DB::rollBack();
                return;
            }

            // Создаем нового пользователя
            // ВАЖНО: Пользователь должен быть новым (проверка выше)
            $user = User::create([
                'telegram_id' => $chatId,
                'name' => 'Telegram User',
                'email' => "telegram_{$chatId}@telegram.local",
                'password' => bcrypt(str()->random(32)),
                'tickets_available' => 3, // Начальное количество билетов для нового пользователя
                'stars_balance' => 0,
                'total_spins' => 0,
                'total_wins' => 0,
            ]);

            // Проверяем, что реферальная связь еще не существует
            $existingReferral = Referral::where('inviter_id', $referrer->id)
                ->where('invited_id', $user->id)
                ->first();

            if ($existingReferral) {
                Log::info('Referral already exists', [
                    'user_id' => $user->id,
                    'referrer_id' => $referrer->id,
                ]);
                DB::rollBack();
                return;
            }

            // Создаем реферальную связь
            Referral::create([
                'inviter_id' => $referrer->id,
                'invited_id' => $user->id,
                'invited_at' => now(),
            ]);

            // Обновляем invited_by у пользователя
            $user->invited_by = $referrer->id;
            $user->save();

            // Начисляем 1 билет рефереру за приглашение
            $referrer->tickets_available = $referrer->tickets_available + 1;
            
            // Если билеты стали больше 0, сбрасываем точку восстановления
            if ($referrer->tickets_available > 0) {
                $referrer->tickets_depleted_at = null;
            }
            
            $referrer->save();

            DB::commit();

            Log::info('Referral registration successful', [
                'user_id' => $user->id,
                'user_telegram_id' => $chatId,
                'referrer_id' => $referrer->id,
                'referrer_telegram_id' => $referrerTelegramId,
                'referrer_tickets_after' => $referrer->tickets_available,
            ]);

            // Отправляем уведомление рефереру о новом реферале
            try {
                TelegramNotificationService::notifyNewReferral($referrer, $user);
                Log::info('Referral notification sent', [
                    'referrer_telegram_id' => $referrerTelegramId,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send referral notification', [
                    'referrer_telegram_id' => $referrerTelegramId,
                    'error' => $e->getMessage(),
                ]);
                // Не прерываем выполнение, если уведомление не отправилось
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in referral registration', [
                'chat_id' => $chatId,
                'referrer_telegram_id' => $referrerTelegramId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

