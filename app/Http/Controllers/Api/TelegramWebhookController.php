<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Telegram\Bot;
use App\Telegram\Keyboard;
use App\Models\User;
use App\Models\Referral;
use App\Models\WheelSetting;
use App\Models\UserTicket;
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
        
        // Извлекаем параметр из команды /start ref_telegramId или /start reftelegramId (для обратной совместимости)
        $text = $message['text'] ?? '';
        $referrerTelegramId = null;
        
        if (str_starts_with($text, '/start ref')) {
            // Извлекаем telegram_id реферера из команды /start ref_telegramId или /start reftelegramId
            $parts = explode(' ', $text);
            if (count($parts) >= 2 && str_starts_with($parts[1], 'ref')) {
                // Поддерживаем оба формата: ref_telegramId и reftelegramId
                $refParam = substr($parts[1], 3); // Убираем префикс "ref"
                // Если есть подчеркивание, убираем его
                if (str_starts_with($refParam, '_')) {
                    $refParam = substr($refParam, 1);
                }
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
        
        // Получаем настройки приветствия из БД
        $settings = WheelSetting::getSettings();
        
        // Получаем текст приветствия (из БД или дефолтный)
        $welcomeText = $settings->welcome_text;
        if (empty($welcomeText)) {
            $welcomeText = "Добро пожаловать в WOW Spin!\n\nКрути рулетку, зови друзей и выигрывай подарки каждый день 🎁";
        }

        // Получаем URL баннера
        $welcomeBannerUrl = $settings->welcome_banner_url;
        
        // Преобразуем относительный путь в полный URL для Telegram API
        if (!empty($welcomeBannerUrl)) {
            // Если это относительный путь (начинается с /), преобразуем в полный URL
            if (str_starts_with($welcomeBannerUrl, '/')) {
                $appUrl = rtrim(config('app.url', ''), '/');
                $welcomeBannerUrl = $appUrl . $welcomeBannerUrl;
            }
        }

        // Получаем кнопки (из БД или дефолтные)
        $welcomeButtons = $settings->welcome_buttons;
        if (empty($welcomeButtons) || !is_array($welcomeButtons)) {
            // Дефолтные кнопки (вторая строка)
            $welcomeButtons = [
                ['label' => 'Наш канал', 'url' => 'https://t.me/WowSpin_news'],
                ['label' => 'Менеджер', 'url' => 'https://t.me/wows_manager'],
            ];
        }

        // Получаем URL Mini App для кнопки рулетки
        // WebApp кнопки требуют HTTPS URL, а не t.me ссылки
        $rouletteMiniAppUrl = config('telegram.mini_app_url');
        if (empty($rouletteMiniAppUrl)) {
            $rouletteMiniAppUrl = rtrim(config('app.url', ''), '/');
        }
        
        // Убеждаемся, что URL заканчивается на / (для корректной работы Mini App)
        if (!empty($rouletteMiniAppUrl) && !str_ends_with($rouletteMiniAppUrl, '/')) {
            $rouletteMiniAppUrl .= '/';
        }

        Log::info('Preparing to send welcome message', [
            'chat_id' => $chatId,
            'has_banner' => !empty($welcomeBannerUrl),
            'buttons_count' => count($welcomeButtons),
            'has_roulette_mini_app_url' => !empty($rouletteMiniAppUrl),
            'roulette_url' => $rouletteMiniAppUrl,
        ]);

        try {
            // Проверяем наличие токена
            $token = config('telegram.bot_token');
            if (!$token) {
                Log::error('Bot token is not configured');
                return;
            }
            
            $bot = new Bot();
            
            // 1. Отправляем баннер (если указан) БЕЗ кнопок
            // Кнопки будут только в текстовом сообщении, чтобы избежать дублирования
            if (!empty($welcomeBannerUrl)) {
                try {
                    $photoParams = [
                        'parse_mode' => 'HTML',
                    ];
                    
                    // НЕ добавляем кнопки к баннеру - они будут только в текстовом сообщении
                    
                    $bot->sendPhoto($chatId, $welcomeBannerUrl, $photoParams);
                    
                    Log::info('Welcome banner sent', [
                        'chat_id' => $chatId,
                        'banner_url' => $welcomeBannerUrl,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send welcome banner', [
                        'chat_id' => $chatId,
                        'banner_url' => $welcomeBannerUrl,
                        'error' => $e->getMessage(),
                    ]);
                    // Продолжаем отправку текстового сообщения даже если баннер не отправился
                }
            }
            
            // 2. Отправляем текстовое сообщение
            $messageParams = [
                'parse_mode' => 'HTML',
            ];

            // Добавляем inline-кнопки
            if (!empty($welcomeButtons) || !empty($rouletteMiniAppUrl)) {
                $keyboard = $this->buildWelcomeKeyboard($welcomeButtons, $rouletteMiniAppUrl);
                if ($keyboard) {
                    $messageParams['reply_markup'] = json_encode($keyboard);
                }
            }

            Log::info('Sending welcome message', [
                'chat_id' => $chatId,
                'text_length' => strlen($welcomeText),
                'has_keyboard' => !empty($messageParams['reply_markup']),
            ]);

            $result = $bot->sendMessage($chatId, $welcomeText, $messageParams);

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

            // Получаем настройки для определения количества стартовых билетов
            $settings = WheelSetting::getSettings();
            $initialTicketsCount = $settings->getValidStartTickets(); // Валидированное значение (по умолчанию 1)

            // Создаем нового пользователя
            // ВАЖНО: Пользователь должен быть новым (проверка выше)
            $user = User::create([
                'telegram_id' => $chatId,
                'name' => 'Telegram User',
                'email' => "telegram_{$chatId}@telegram.local",
                'password' => bcrypt(str()->random(32)),
                'tickets_available' => $initialTicketsCount, // Используем настройку из админки
                'stars_balance' => 0,
                'total_spins' => 0,
                'total_wins' => 0,
            ]);

            // Создаем запись в user_tickets для отслеживания источника стартовых билетов
            UserTicket::create([
                'user_id' => $user->id,
                'tickets_count' => $initialTicketsCount,
                'restored_at' => null, // Стартовые билеты доступны сразу
                'source' => 'initial_bonus',
            ]);
            
            Log::info('Initial tickets granted to new user (from referral)', [
                'user_id' => $user->id,
                'telegram_id' => $chatId,
                'initial_tickets_count' => $initialTicketsCount,
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
            $ticketsBefore = $referrer->tickets_available;
            
            // Логируем начисление билетов ДО изменения
            Log::info('Adding ticket for referral', [
                'referrer_id' => $referrer->id,
                'referrer_telegram_id' => $referrer->telegram_id,
                'new_user_id' => $user->id,
                'new_user_telegram_id' => $chatId,
                'tickets_before' => $ticketsBefore,
                'tickets_to_add' => 1,
            ]);
            
            $referrer->tickets_available = $referrer->tickets_available + 1;
            
            // Если билеты стали больше 0, сбрасываем точку восстановления
            if ($referrer->tickets_available > 0) {
                $referrer->tickets_depleted_at = null;
            }
            
            $referrer->save();
            
            // Логируем начисление билетов ПОСЛЕ изменения
            Log::info('Ticket added for referral', [
                'referrer_id' => $referrer->id,
                'referrer_telegram_id' => $referrer->telegram_id,
                'new_user_id' => $user->id,
                'new_user_telegram_id' => $chatId,
                'tickets_before' => $ticketsBefore,
                'tickets_after' => $referrer->tickets_available,
                'tickets_added' => 1,
                'timestamp' => now()->toIso8601String(),
            ]);

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

    /**
     * Построить клавиатуру для приветственного сообщения
     * 
     * Структура:
     * - Первая строка: одна широкая кнопка "🧡 ПЕРЕЙТИ В РУЛЕТКУ 🧡" (WebApp)
     * - Вторая строка: две URL кнопки из настроек
     * 
     * @param array $buttons Массив кнопок [['label' => '...', 'url' => '...'], ...] (для второй строки)
     * @param string|null $rouletteMiniAppUrl URL Mini App для кнопки рулетки
     * @return array|null
     */
    protected function buildWelcomeKeyboard(array $buttons, ?string $rouletteMiniAppUrl = null): ?array
    {
        if (empty($buttons) && empty($rouletteMiniAppUrl)) {
            return null;
        }
        
        $inlineKeyboard = [];
        
        // Первая строка: одна широкая кнопка "🧡 ПЕРЕЙТИ В РУЛЕТКУ 🧡"
        if (!empty($rouletteMiniAppUrl)) {
            $inlineKeyboard[] = [
                [
                    'text' => '🧡 ПЕРЕЙТИ В РУЛЕТКУ 🧡',
                    'web_app' => ['url' => $rouletteMiniAppUrl]
                ]
            ];
        }
        
        // Вторая строка: две URL кнопки
        if (!empty($buttons) && is_array($buttons)) {
            $urlButtons = [];
            foreach ($buttons as $button) {
                if (isset($button['label']) && isset($button['url']) && !empty($button['label']) && !empty($button['url'])) {
                    $urlButtons[] = [
                        'text' => $button['label'],
                        'url' => $button['url']
                    ];
                    // Ограничиваем максимум 2 кнопки во второй строке
                    if (count($urlButtons) >= 2) {
                        break;
                    }
                }
            }
            
            // Добавляем кнопки второй строки
            if (!empty($urlButtons)) {
                $inlineKeyboard[] = $urlButtons;
            }
        }
        
        // Если клавиатура пустая, возвращаем null
        if (empty($inlineKeyboard)) {
            return null;
        }
        
        return [
            'inline_keyboard' => $inlineKeyboard
        ];
    }
}

