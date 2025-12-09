<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDeployToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Получаем токен из заголовка или тела запроса
        $token = $request->header('X-Deploy-Token') 
            ?? $request->header('X-Deploy-Secret')
            ?? $request->input('token')
            ?? $request->input('secret');
        
        // Пытаемся получить ожидаемый токен из разных источников
        $expectedToken = config('app.deploy_token');
        
        // Если не найден в конфиге, читаем напрямую из .env
        if (!$expectedToken) {
            $envPath = base_path('.env');
            if (file_exists($envPath)) {
                $envContent = file_get_contents($envPath);
                
                // Сначала пробуем DEPLOY_TOKEN
                if (preg_match('/^DEPLOY_TOKEN=(.+)$/m', $envContent, $matches)) {
                    $expectedToken = trim($matches[1], ' "\'');
                }
                
                // Если не найден, пробуем DEPLOY_SECRET (для обратной совместимости)
                if (!$expectedToken && preg_match('/^DEPLOY_SECRET=(.+)$/m', $envContent, $matches)) {
                    $expectedToken = trim($matches[1], ' "\'');
                }
            }
        }
        
        // Если все еще не найден, пробуем env()
        if (!$expectedToken) {
            $expectedToken = env('DEPLOY_TOKEN') ?? env('DEPLOY_SECRET');
        }

        if (!$expectedToken) {
            return response()->json([
                'success' => false,
                'message' => 'DEPLOY_TOKEN или DEPLOY_SECRET не настроен на сервере. Добавьте DEPLOY_TOKEN в .env файл и выполните: php artisan config:clear',
            ], 500);
        }

        if (!$token || $token !== $expectedToken) {
            return response()->json([
                'success' => false,
                'message' => 'Неверный секретный ключ',
            ], 403);
        }

        return $next($request);
    }
}

