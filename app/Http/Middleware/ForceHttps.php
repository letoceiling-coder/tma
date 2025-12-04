<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\URL;

/**
 * Middleware для принудительного использования HTTPS
 * и исправления URL (удаление /public/)
 */
class ForceHttps
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Принудительно используем HTTPS в production
        if (!$request->secure() && config('app.env') === 'production') {
            $uri = $request->getRequestUri();
            // Убираем /public/ из URI, если он там есть
            $uri = str_replace('/public', '', $uri);
            return redirect()->secure($uri);
        }

        // Исправляем APP_URL, если он содержит /public/ или использует HTTP
        $appUrl = config('app.url');
        $needsUpdate = false;
        
        if ($appUrl) {
            // Убираем /public/ из URL
            if (str_contains($appUrl, '/public')) {
                $appUrl = str_replace('/public', '', $appUrl);
                $needsUpdate = true;
            }
            
            // Заменяем HTTP на HTTPS
            if (str_starts_with($appUrl, 'http://')) {
                $appUrl = str_replace('http://', 'https://', $appUrl);
                $needsUpdate = true;
            }
            
            if ($needsUpdate) {
                config(['app.url' => $appUrl]);
                // Устанавливаем базовый URL для генерации URL
                URL::forceRootUrl($appUrl);
                URL::forceScheme('https');
            }
        }

        $response = $next($request);

        return $response;
    }
}

