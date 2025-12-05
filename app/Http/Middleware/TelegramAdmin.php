<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware для проверки прав администратора
 */
class TelegramAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $telegramUserId = $request->input('telegram_user_id') ?? $request->get('telegram_user_id');

        if (!$telegramUserId) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Telegram user ID not found'
            ], 401);
        }

        $adminIds = config('telegram.admin_ids', []);

        if (!in_array($telegramUserId, $adminIds)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Access denied. Admin rights required.'
            ], 403);
        }

        return $next($request);
    }
}


