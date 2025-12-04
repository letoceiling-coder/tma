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
        // Логируем входящий запрос
        $path = $request->path();
        $fullUrl = $request->fullUrl();
        $appUrl = config('app.url');
        
        \Log::info('ForceHttps Middleware - Incoming Request', [
            'path' => $path,
            'full_url' => $fullUrl,
            'secure' => $request->secure(),
            'app_url' => $appUrl,
            'request_uri' => $request->getRequestUri(),
            'scheme' => $request->getScheme(),
            'host' => $request->getHost(),
        ]);
        
        // Принудительно используем HTTPS только в production и только для реальных доменов
        // Не применяем для локальных доменов (.loc, .local, localhost)
        $host = $request->getHost();
        $isLocalDomain = str_contains($host, '.loc') || 
                         str_contains($host, '.local') || 
                         $host === 'localhost' || 
                         str_starts_with($host, '127.0.0.1') ||
                         str_starts_with($host, '192.168.');
        
        if (!$request->secure() && config('app.env') === 'production' && !$isLocalDomain) {
            $uri = $request->getRequestUri();
            // Убираем /public/ из URI, если он там есть
            $uri = str_replace('/public', '', $uri);
            \Log::info('ForceHttps Middleware - Redirecting to HTTPS', ['uri' => $uri]);
            return redirect()->secure($uri);
        }

        // Для локальных доменов явно сбрасываем принудительный HTTPS
        if ($isLocalDomain) {
            URL::forceScheme(null);
            \Log::info('ForceHttps Middleware - Local domain detected, forcing scheme to null', ['host' => $host]);
        }
        
        // Исправляем APP_URL, если он содержит /public/ или использует HTTP
        // Но только для production и не для локальных доменов
        $needsUpdate = false;
        
        if ($appUrl && !$isLocalDomain) {
            // Убираем /public/ из URL
            if (str_contains($appUrl, '/public')) {
                $appUrl = str_replace('/public', '', $appUrl);
                $needsUpdate = true;
                \Log::info('ForceHttps Middleware - Removing /public/ from APP_URL', ['old' => config('app.url'), 'new' => $appUrl]);
            }
            
            // Заменяем HTTP на HTTPS только в production
            if (str_starts_with($appUrl, 'http://') && config('app.env') === 'production') {
                $appUrl = str_replace('http://', 'https://', $appUrl);
                $needsUpdate = true;
                \Log::info('ForceHttps Middleware - Replacing http:// with https://', ['old' => config('app.url'), 'new' => $appUrl]);
            }
            
            if ($needsUpdate) {
                config(['app.url' => $appUrl]);
                // Устанавливаем базовый URL для генерации URL
                URL::forceRootUrl($appUrl);
                // Принудительно используем HTTPS только в production
                if (config('app.env') === 'production') {
                    URL::forceScheme('https');
                }
                \Log::info('ForceHttps Middleware - Updated APP_URL', [
                    'app_url' => config('app.url'),
                    'forced_root_url' => $appUrl,
                    'forced_scheme' => config('app.env') === 'production' ? 'https' : 'auto',
                ]);
            }
        }

        $response = $next($request);

        // Логируем после обработки
        \Log::info('ForceHttps Middleware - After processing', [
            'app_url' => config('app.url'),
            'response_status' => $response->getStatusCode(),
        ]);

        return $response;
    }
}

