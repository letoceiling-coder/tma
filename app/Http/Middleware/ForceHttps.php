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
        
        // Принудительно используем HTTPS в production
        if (!$request->secure() && config('app.env') === 'production') {
            $uri = $request->getRequestUri();
            // Убираем /public/ из URI, если он там есть
            $uri = str_replace('/public', '', $uri);
            \Log::info('ForceHttps Middleware - Redirecting to HTTPS', ['uri' => $uri]);
            return redirect()->secure($uri);
        }

        // Исправляем APP_URL, если он содержит /public/ или использует HTTP
        $needsUpdate = false;
        
        if ($appUrl) {
            // Убираем /public/ из URL
            if (str_contains($appUrl, '/public')) {
                $appUrl = str_replace('/public', '', $appUrl);
                $needsUpdate = true;
                \Log::info('ForceHttps Middleware - Removing /public/ from APP_URL', ['old' => config('app.url'), 'new' => $appUrl]);
            }
            
            // Заменяем HTTP на HTTPS
            if (str_starts_with($appUrl, 'http://')) {
                $appUrl = str_replace('http://', 'https://', $appUrl);
                $needsUpdate = true;
                \Log::info('ForceHttps Middleware - Replacing http:// with https://', ['old' => config('app.url'), 'new' => $appUrl]);
            }
            
            if ($needsUpdate) {
                config(['app.url' => $appUrl]);
                // Устанавливаем базовый URL для генерации URL
                URL::forceRootUrl($appUrl);
                URL::forceScheme('https');
                \Log::info('ForceHttps Middleware - Updated APP_URL and forced HTTPS', [
                    'app_url' => config('app.url'),
                    'forced_root_url' => $appUrl,
                    'forced_scheme' => 'https',
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

