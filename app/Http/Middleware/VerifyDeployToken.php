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
        $token = $request->header('X-Deploy-Token') ?? $request->input('token');
        
        // Используем config() для работы после кеширования конфигурации
        // Если конфиг закеширован, используем config(), иначе env()
        $expectedToken = config('app.deploy_token') ?? env('DEPLOY_TOKEN');

        if (!$expectedToken) {
            return response()->json([
                'success' => false,
                'message' => 'DEPLOY_TOKEN не настроен на сервере. Проверьте .env и выполните: php artisan config:clear',
            ], 500);
        }

        if (!$token || $token !== $expectedToken) {
            return response()->json([
                'success' => false,
                'message' => 'Неверный токен деплоя',
            ], 401);
        }

        return $next($request);
    }
}

