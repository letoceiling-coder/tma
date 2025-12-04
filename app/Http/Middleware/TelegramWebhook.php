<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware для проверки webhook запросов от Telegram
 */
class TelegramWebhook
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Проверка secret token (если настроен)
        $secretToken = config('telegram.webhook.secret_token');
        
        if ($secretToken) {
            $receivedToken = $request->header('X-Telegram-Bot-Api-Secret-Token');
            
            if ($receivedToken !== $secretToken) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Invalid secret token'
                ], 401);
            }
        }

        // Проверка IP адреса (опционально)
        // Telegram использует диапазон 149.154.160.0/20 и 91.108.4.0/22
        if (config('telegram.webhook.verify_ip', false)) {
            $ip = $request->ip();
            
            if (!$this->isTelegramIp($ip)) {
                \Log::warning('Webhook request from non-Telegram IP', ['ip' => $ip]);
                
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'Invalid source IP'
                ], 403);
            }
        }

        return $next($request);
    }

    /**
     * Проверить, что IP принадлежит Telegram
     */
    protected function isTelegramIp(string $ip): bool
    {
        $telegramRanges = [
            '149.154.160.0/20',
            '91.108.4.0/22',
        ];

        foreach ($telegramRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Проверить, находится ли IP в диапазоне
     */
    protected function ipInRange(string $ip, string $range): bool
    {
        list($subnet, $mask) = explode('/', $range);
        
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = -1 << (32 - (int)$mask);
        
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}

