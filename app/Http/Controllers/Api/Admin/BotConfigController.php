<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class BotConfigController extends Controller
{
    /**
     * Получить токен бота из конфига или .env
     */
    protected function getBotToken(): ?string
    {
        $token = config('telegram.bot_token');
        
        // Если токен не найден в конфиге, читаем напрямую из .env
        if (!$token) {
            $envPath = base_path('.env');
            if (File::exists($envPath)) {
                $envContent = File::get($envPath);
                if (preg_match('/^TELEGRAM_BOT_TOKEN=(.+)$/m', $envContent, $matches)) {
                    $token = trim($matches[1], '"\'');
                }
            }
        }
        
        return $token ?: null;
    }

    /**
     * Получить текущие настройки бота
     */
    public function index()
    {
        return response()->json([
            'bot_token' => config('telegram.bot_token'),
            'mini_app_url' => config('telegram.mini_app_url'),
            'welcome_message' => config('telegram.welcome_message'),
        ]);
    }

    /**
     * Сохранить настройки бота
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'bot_token' => 'nullable|string',
            'mini_app_url' => 'nullable|url',
            'welcome_message.enabled' => 'boolean',
            'welcome_message.text' => 'nullable|string',
            'welcome_message.mini_app_button.enabled' => 'boolean',
            'welcome_message.mini_app_button.text' => 'nullable|string',
            'welcome_message.mini_app_button.url' => 'nullable|url',
        ]);

        $envPath = base_path('.env');
        $envContent = File::get($envPath);

        // Обновляем переменные окружения
        if (isset($validated['bot_token'])) {
            $envContent = preg_replace(
                '/^TELEGRAM_BOT_TOKEN=.*/m',
                'TELEGRAM_BOT_TOKEN=' . $validated['bot_token'],
                $envContent
            );
            if (!preg_match('/^TELEGRAM_BOT_TOKEN=/m', $envContent)) {
                $envContent .= "\nTELEGRAM_BOT_TOKEN=" . $validated['bot_token'];
            }
        }

        if (isset($validated['mini_app_url'])) {
            $envContent = preg_replace(
                '/^TELEGRAM_MINI_APP_URL=.*/m',
                'TELEGRAM_MINI_APP_URL=' . $validated['mini_app_url'],
                $envContent
            );
        }

        if (isset($validated['welcome_message'])) {
            $wm = $validated['welcome_message'];
            
            if (isset($wm['enabled'])) {
                $envContent = preg_replace(
                    '/^TELEGRAM_WELCOME_MESSAGE_ENABLED=.*/m',
                    'TELEGRAM_WELCOME_MESSAGE_ENABLED=' . ($wm['enabled'] ? 'true' : 'false'),
                    $envContent
                );
            }

            if (isset($wm['text'])) {
                $text = addslashes($wm['text']);
                $envContent = preg_replace(
                    '/^TELEGRAM_WELCOME_MESSAGE_TEXT=.*/m',
                    'TELEGRAM_WELCOME_MESSAGE_TEXT="' . $text . '"',
                    $envContent
                );
            }

            if (isset($wm['mini_app_button'])) {
                $btn = $wm['mini_app_button'];
                
                if (isset($btn['enabled'])) {
                    $envContent = preg_replace(
                        '/^TELEGRAM_WELCOME_MINI_APP_BUTTON_ENABLED=.*/m',
                        'TELEGRAM_WELCOME_MINI_APP_BUTTON_ENABLED=' . ($btn['enabled'] ? 'true' : 'false'),
                        $envContent
                    );
                }

                if (isset($btn['text'])) {
                    $envContent = preg_replace(
                        '/^TELEGRAM_WELCOME_MINI_APP_BUTTON_TEXT=.*/m',
                        'TELEGRAM_WELCOME_MINI_APP_BUTTON_TEXT=' . $btn['text'],
                        $envContent
                    );
                }

                if (isset($btn['url'])) {
                    // Если URL пустой, не сохраняем его (будет использоваться из основных настроек)
                    if (!empty($btn['url'])) {
                        $envContent = preg_replace(
                            '/^TELEGRAM_WELCOME_MINI_APP_BUTTON_URL=.*/m',
                            'TELEGRAM_WELCOME_MINI_APP_BUTTON_URL=' . $btn['url'],
                            $envContent
                        );
                        if (!preg_match('/^TELEGRAM_WELCOME_MINI_APP_BUTTON_URL=/m', $envContent)) {
                            $envContent .= "\nTELEGRAM_WELCOME_MINI_APP_BUTTON_URL=" . $btn['url'];
                        }
                    } else {
                        // Удаляем пустой URL из .env, чтобы использовался из основных настроек
                        $envContent = preg_replace('/^TELEGRAM_WELCOME_MINI_APP_BUTTON_URL=.*\n?/m', '', $envContent);
                    }
                }
            }
        }

        File::put($envPath, $envContent);

        // Очищаем кеш конфигурации
        \Artisan::call('config:clear');

        // Автоматически устанавливаем webhook при сохранении токена
        $webhookError = null;
        if (isset($validated['bot_token']) && !empty($validated['bot_token'])) {
            try {
                // Получаем APP_URL из .env файла напрямую
                $appUrl = null;
                if (preg_match('/^APP_URL=(.+)$/m', $envContent, $matches)) {
                    $appUrl = trim($matches[1], '"\'');
                }
                
                // Если APP_URL не найден в .env, используем URL из запроса
                if (empty($appUrl) || $appUrl === 'http://localhost') {
                    $appUrl = rtrim($request->getSchemeAndHttpHost(), '/');
                }
                
                $appUrl = rtrim($appUrl, '/');
                $webhookUrl = $appUrl . '/api/telegram/webhook';
                
                // Исправляем двойные слеши в URL
                $webhookUrl = preg_replace('#([^:])//+#', '$1/', $webhookUrl);
                
                $token = $validated['bot_token'];
                
                // Получаем secret_token из .env
                $secretToken = null;
                if (preg_match('/^TELEGRAM_WEBHOOK_SECRET_TOKEN=(.+)$/m', $envContent, $matches)) {
                    $secretToken = trim($matches[1], '"\'');
                }

                $params = [
                    'url' => $webhookUrl,
                ];

                if ($secretToken) {
                    $params['secret_token'] = $secretToken;
                }

                $response = \Http::post("https://api.telegram.org/bot{$token}/setWebhook", $params);
                $result = $response->json();
                
                if (!($result['ok'] ?? false)) {
                    $errorDescription = $result['description'] ?? 'Неизвестная ошибка';
                    $webhookError = "Не удалось установить webhook: {$errorDescription}";
                    \Log::warning('Failed to set webhook automatically', [
                        'response' => $result,
                        'webhook_url' => $webhookUrl,
                    ]);
                } else {
                    \Log::info('Webhook set automatically', [
                        'webhook_url' => $webhookUrl,
                    ]);
                }
            } catch (\Exception $e) {
                $webhookError = "Ошибка установки webhook: " . $e->getMessage();
                \Log::error('Error setting webhook automatically', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $responseData = ['message' => 'Настройки сохранены'];
        if ($webhookError) {
            $responseData['webhook_error'] = $webhookError;
        }

        return response()->json($responseData);
    }

    /**
     * Получить информацию о webhook
     */
    public function getWebhookInfo()
    {
        try {
            $token = $this->getBotToken();
            
            if (!$token) {
                return response()->json(['error' => 'Токен бота не установлен'], 400);
            }

            $response = \Http::get("https://api.telegram.org/bot{$token}/getWebhookInfo");
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Тест подключения к боту
     */
    public function testConnection()
    {
        try {
            $token = $this->getBotToken();
            if (!$token) {
                return response()->json(['error' => 'Токен бота не установлен'], 400);
            }

            $response = \Http::get("https://api.telegram.org/bot{$token}/getMe");
            $data = $response->json();

            if ($data['ok'] ?? false) {
                return response()->json(['success' => true, 'bot' => $data['result']]);
            }

            return response()->json(['error' => 'Не удалось подключиться к боту'], 400);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Установить webhook
     */
    public function setWebhook(Request $request)
    {
        try {
            $token = config('telegram.bot_token');
            if (!$token) {
                return response()->json(['error' => 'Токен бота не установлен'], 400);
            }

            $webhookUrl = $request->input('url', config('telegram.webhook_url'));
            // Если URL не задан, формируем из APP_URL
            if (!$webhookUrl) {
                $appUrl = rtrim(config('app.url', ''), '/');
                $webhookUrl = $appUrl . '/api/telegram/webhook';
            }
            // Исправляем двойные слеши в URL
            $webhookUrl = preg_replace('#([^:])//+#', '$1/', $webhookUrl);
            
            $secretToken = config('telegram.webhook.secret_token');

            $params = [
                'url' => $webhookUrl,
            ];

            if ($secretToken) {
                $params['secret_token'] = $secretToken;
            }

            $response = \Http::post("https://api.telegram.org/bot{$token}/setWebhook", $params);
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Удалить webhook
     */
    public function deleteWebhook()
    {
        try {
            $token = config('telegram.bot_token');
            if (!$token) {
                return response()->json(['error' => 'Токен бота не установлен'], 400);
            }

            $response = \Http::post("https://api.telegram.org/bot{$token}/deleteWebhook");
            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}

