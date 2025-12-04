<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateTelegramInitData
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Получаем initData из заголовка или query параметра
        $initData = $request->header('X-Telegram-Init-Data') ?? $request->query('initData');
        
        if (!$initData) {
            return response()->json([
                'error' => 'Telegram initData is required',
                'message' => 'Init data not provided'
            ], 401);
        }

        // Получаем токен бота
        $botToken = config('services.telegram.bot_token');
        
        if (!$botToken) {
            Log::warning('Telegram bot token not configured');
            // В режиме разработки разрешаем запрос без валидации
            if (config('app.debug')) {
                return $next($request);
            }
            return response()->json([
                'error' => 'Telegram bot token not configured',
                'message' => 'Server configuration error'
            ], 500);
        }

        // Валидируем подпись
        $isValid = TelegramService::validateInitData($initData, $botToken);
        
        if (!$isValid) {
            // В режиме разработки разрешаем запрос без валидации
            if (config('app.debug')) {
                Log::warning('Telegram initData validation failed in debug mode, allowing request');
                return $next($request);
            }
            
            return response()->json([
                'error' => 'Invalid Telegram initData signature',
                'message' => 'Authentication failed'
            ], 401);
        }

        // Добавляем initData в request для использования в контроллерах
        $request->merge(['telegram_init_data' => $initData]);

        return $next($request);
    }
}

