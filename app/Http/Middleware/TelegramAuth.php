<?php

namespace App\Http\Middleware;

use App\Telegram\MiniApp;
use App\Telegram\Exceptions\TelegramValidationException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware для аутентификации Telegram Mini App
 */
class TelegramAuth
{
    protected MiniApp $miniApp;

    public function __construct()
    {
        $this->miniApp = app('telegram.miniapp');
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $initData = $request->header('X-Telegram-Init-Data');

        if (!$initData) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Telegram init data not provided'
            ], 401);
        }

        try {
            // Валидация initData
            if (!$this->miniApp->validateInitData($initData)) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Invalid Telegram signature'
                ], 401);
            }

            // Проверка срока действия
            if ($this->miniApp->isInitDataExpired($initData)) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Init data expired'
                ], 401);
            }

            // Добавляем данные пользователя в request
            $user = $this->miniApp->getUser($initData);
            $request->merge([
                'telegram_user' => $user,
                'telegram_user_id' => $user['id'] ?? null,
            ]);

            return $next($request);

        } catch (TelegramValidationException $e) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => $e->getMessage()
            ], 401);
        }
    }
}


