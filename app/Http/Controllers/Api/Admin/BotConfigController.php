<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Telegram\Bot;
use App\Telegram\Exceptions\TelegramException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class BotConfigController extends Controller
{
    /**
     * Получить текущие настройки бота
     */
    public function index(Request $request)
    {
        // Логируем информацию о запросе
        Log::info('BotConfigController@index - Request received', [
            'path' => $request->path(),
            'full_url' => $request->fullUrl(),
            'url' => $request->url(),
            'app_url' => config('app.url'),
            'request_uri' => $request->getRequestUri(),
            'scheme' => $request->getScheme(),
            'secure' => $request->secure(),
            'host' => $request->getHost(),
        ]);
        
        $config = config('telegram');
        
        return response()->json([
            'bot_token' => $config['bot_token'] ?? '',
            'bot_username' => $config['bot_username'] ?? '',
            'webhook_url' => $config['webhook_url'] ?? '',
            'mini_app_url' => $config['mini_app_url'] ?? '',
            'admin_ids' => $config['admin_ids'] ?? [],
            'required_channels' => $config['required_channels'] ?? [],
            'webhook' => [
                'secret_token' => $config['webhook']['secret_token'] ?? '',
                'allowed_updates' => $config['webhook']['allowed_updates'] ?? [],
                'max_connections' => $config['webhook']['max_connections'] ?? 40,
            ],
            'notifications' => [
                'enabled' => $config['notifications']['enabled'] ?? true,
                'queue' => $config['notifications']['queue'] ?? 'default',
            ],
            'rate_limiting' => [
                'enabled' => $config['rate_limiting']['enabled'] ?? true,
                'cache_driver' => $config['rate_limiting']['cache_driver'] ?? 'redis',
            ],
            'validation' => [
                'enabled' => $config['validation']['enabled'] ?? true,
                'auto_truncate' => $config['validation']['auto_truncate'] ?? true,
            ],
            'welcome_message' => [
                'enabled' => $config['welcome_message']['enabled'] ?? true,
                'text' => $config['welcome_message']['text'] ?? '<b>Добро пожаловать!</b>',
                'mini_app_button' => [
                    'enabled' => $config['welcome_message']['mini_app_button']['enabled'] ?? true,
                    'text' => $config['welcome_message']['mini_app_button']['text'] ?? '🚀 Открыть приложение',
                    'url' => $config['welcome_message']['mini_app_button']['url'] ?? '',
                ],
            ],
        ]);
    }

    /**
     * Сохранить настройки бота
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'bot_token' => 'nullable|string',
            'bot_username' => 'nullable|string',
            'webhook_url' => 'nullable|url',
            'mini_app_url' => 'nullable|url',
            'admin_ids' => 'nullable|array',
            'admin_ids.*' => 'integer',
            'required_channels' => 'nullable|array',
            'required_channels.*' => 'string',
            'webhook.secret_token' => 'nullable|string',
            'webhook.allowed_updates' => 'nullable|array',
            'webhook.max_connections' => 'nullable|integer|min:1|max:100',
            'notifications.enabled' => 'nullable|boolean',
            'notifications.queue' => 'nullable|string',
            'rate_limiting.enabled' => 'nullable|boolean',
            'rate_limiting.cache_driver' => 'nullable|string',
            'validation.enabled' => 'nullable|boolean',
            'validation.auto_truncate' => 'nullable|boolean',
            'welcome_message.enabled' => 'nullable|boolean',
            'welcome_message.text' => 'nullable|string',
            'welcome_message.mini_app_button.enabled' => 'nullable|boolean',
            'welcome_message.mini_app_button.text' => 'nullable|string|max:64',
            'welcome_message.mini_app_button.url' => 'nullable|url',
        ]);

        // Обновляем .env файл
        $envPath = base_path('.env');
        if (!file_exists($envPath)) {
            return response()->json(['message' => 'Файл .env не найден'], 500);
        }

        $envContent = file_get_contents($envPath);

        // Функция для обновления значения в .env
        $updateEnvValue = function($key, $value) use (&$envContent) {
            if (preg_match("/^{$key}=.*/m", $envContent)) {
                $envContent = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $envContent);
            } else {
                $envContent .= "\n{$key}={$value}";
            }
        };

        // Обновляем основные значения
        if (isset($validated['bot_token'])) {
            $updateEnvValue('TELEGRAM_BOT_TOKEN', $validated['bot_token']);
        }
        if (isset($validated['bot_username'])) {
            $updateEnvValue('TELEGRAM_BOT_USERNAME', $validated['bot_username']);
        }
        if (isset($validated['webhook_url'])) {
            $updateEnvValue('TELEGRAM_WEBHOOK_URL', $validated['webhook_url']);
        }
        if (isset($validated['mini_app_url'])) {
            $updateEnvValue('TELEGRAM_MINI_APP_URL', $validated['mini_app_url']);
        }
        if (isset($validated['admin_ids'])) {
            $updateEnvValue('TELEGRAM_ADMIN_IDS', implode(',', $validated['admin_ids']));
        }
        if (isset($validated['required_channels'])) {
            $updateEnvValue('TELEGRAM_REQUIRED_CHANNELS', implode(',', $validated['required_channels']));
        }

        // Обрабатываем вложенные массивы
        if (isset($validated['webhook']['secret_token'])) {
            $updateEnvValue('TELEGRAM_WEBHOOK_SECRET', $validated['webhook']['secret_token']);
        }

        if (isset($validated['notifications']['enabled'])) {
            $updateEnvValue('TELEGRAM_NOTIFICATIONS_ENABLED', $validated['notifications']['enabled'] ? 'true' : 'false');
        }
        if (isset($validated['notifications']['queue'])) {
            $updateEnvValue('TELEGRAM_NOTIFICATIONS_QUEUE', $validated['notifications']['queue']);
        }

        if (isset($validated['rate_limiting']['enabled'])) {
            $updateEnvValue('TELEGRAM_RATE_LIMITING_ENABLED', $validated['rate_limiting']['enabled'] ? 'true' : 'false');
        }
        if (isset($validated['rate_limiting']['cache_driver'])) {
            $updateEnvValue('TELEGRAM_RATE_LIMITING_CACHE', $validated['rate_limiting']['cache_driver']);
        }

        if (isset($validated['validation']['enabled'])) {
            $updateEnvValue('TELEGRAM_VALIDATION_ENABLED', $validated['validation']['enabled'] ? 'true' : 'false');
        }
        if (isset($validated['validation']['auto_truncate'])) {
            $updateEnvValue('TELEGRAM_AUTO_TRUNCATE', $validated['validation']['auto_truncate'] ? 'true' : 'false');
        }

        // Обрабатываем приветственное сообщение
        if (isset($validated['welcome_message']['enabled'])) {
            $updateEnvValue('TELEGRAM_WELCOME_MESSAGE_ENABLED', $validated['welcome_message']['enabled'] ? 'true' : 'false');
        }
        if (isset($validated['welcome_message']['text'])) {
            // Сохраняем текст как есть, но заменяем переносы строк на \n для .env
            $text = $validated['welcome_message']['text'];
            // Экранируем кавычки и переносы строк
            $text = str_replace(['"', "\n", "\r"], ['\"', '\\n', '\\r'], $text);
            $updateEnvValue('TELEGRAM_WELCOME_MESSAGE_TEXT', '"' . $text . '"');
        }
        if (isset($validated['welcome_message']['mini_app_button']['enabled'])) {
            $updateEnvValue('TELEGRAM_WELCOME_MINI_APP_BUTTON_ENABLED', $validated['welcome_message']['mini_app_button']['enabled'] ? 'true' : 'false');
        }
        if (isset($validated['welcome_message']['mini_app_button']['text'])) {
            $updateEnvValue('TELEGRAM_WELCOME_MINI_APP_BUTTON_TEXT', $validated['welcome_message']['mini_app_button']['text']);
        }
        if (isset($validated['welcome_message']['mini_app_button']['url'])) {
            $updateEnvValue('TELEGRAM_WELCOME_MINI_APP_BUTTON_URL', $validated['welcome_message']['mini_app_button']['url']);
        }

        file_put_contents($envPath, $envContent);

        // Очищаем кеш конфигурации
        Artisan::call('config:clear');

        // Если указан токен, автоматически регистрируем webhook
        if (!empty($validated['bot_token']) && !empty($validated['webhook_url'])) {
            try {
                $bot = new Bot($validated['bot_token']);
                
                $webhookParams = [];
                
                if (!empty($validated['webhook']['secret_token'])) {
                    $webhookParams['secret_token'] = $validated['webhook']['secret_token'];
                }
                
                if (!empty($validated['webhook']['allowed_updates'])) {
                    $webhookParams['allowed_updates'] = $validated['webhook']['allowed_updates'];
                }
                
                if (!empty($validated['webhook']['max_connections'])) {
                    $webhookParams['max_connections'] = $validated['webhook']['max_connections'];
                }

                $bot->setWebhook($validated['webhook_url'], $webhookParams);
                
                Log::info('Webhook registered automatically', [
                    'url' => $validated['webhook_url'],
                ]);
            } catch (TelegramException $e) {
                Log::error('Failed to register webhook automatically', [
                    'error' => $e->getMessage(),
                ]);
                
                return response()->json([
                    'message' => 'Настройки сохранены, но не удалось зарегистрировать webhook: ' . $e->getMessage(),
                    'error' => true,
                ], 422);
            }
        }

        return response()->json([
            'message' => 'Настройки успешно сохранены' . (!empty($validated['bot_token']) && !empty($validated['webhook_url']) ? ' и webhook зарегистрирован' : ''),
        ]);
    }

    /**
     * Получить информацию о webhook
     */
    public function getWebhookInfo()
    {
        try {
            $bot = new Bot();
            $info = $bot->getWebhookInfo();
            
            return response()->json([
                'success' => true,
                'data' => $info,
            ]);
        } catch (TelegramException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Тест подключения к боту
     */
    public function testConnection()
    {
        try {
            $bot = new Bot();
            $me = $bot->getMe();
            
            return response()->json([
                'success' => true,
                'data' => $me,
                'message' => 'Подключение успешно!',
            ]);
        } catch (TelegramException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Установить webhook вручную
     */
    public function setWebhook(Request $request)
    {
        $validated = $request->validate([
            'url' => 'required|url',
            'secret_token' => 'nullable|string',
            'allowed_updates' => 'nullable|array',
            'max_connections' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $bot = new Bot();
            
            $params = [];
            if (!empty($validated['secret_token'])) {
                $params['secret_token'] = $validated['secret_token'];
            }
            if (!empty($validated['allowed_updates'])) {
                $params['allowed_updates'] = $validated['allowed_updates'];
            }
            if (!empty($validated['max_connections'])) {
                $params['max_connections'] = $validated['max_connections'];
            }

            $result = $bot->setWebhook($validated['url'], $params);
            
            return response()->json([
                'success' => true,
                'message' => 'Webhook успешно установлен',
                'data' => $result,
            ]);
        } catch (TelegramException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Удалить webhook
     */
    public function deleteWebhook(Request $request)
    {
        $dropPendingUpdates = $request->input('drop_pending_updates', false);

        try {
            $bot = new Bot();
            $result = $bot->deleteWebhook($dropPendingUpdates);
            
            return response()->json([
                'success' => true,
                'message' => 'Webhook успешно удален',
                'data' => $result,
            ]);
        } catch (TelegramException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}

