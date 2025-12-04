import axios from 'axios';
window.axios = axios;

// Устанавливаем baseURL на основе текущего протокола и хоста
// Это предотвращает Mixed Content ошибки (HTTPS страница -> HTTP запрос)
if (typeof window !== 'undefined') {
    // Используем относительный путь, чтобы избежать проблем с Mixed Content
    // axios будет использовать текущий протокол (HTTPS) автоматически
    window.axios.defaults.baseURL = '';
    
    // Добавляем interceptor для исправления URL, если они содержат /public/ или используют HTTP
    window.axios.interceptors.request.use((config) => {
        // Функция для исправления URL
        const fixUrl = (url) => {
            if (!url || typeof url !== 'string') return url;
            
            let fixed = url;
            
            // Убираем /public/ из URL (все вхождения, включая в начале)
            fixed = fixed.replace(/\/public\//g, '/');
            fixed = fixed.replace(/^\/public\//, '/');
            fixed = fixed.replace(/\/public$/, '');
            
            // Заменяем HTTP на текущий протокол (HTTPS)
            if (fixed.startsWith('http://')) {
                fixed = fixed.replace('http://', window.location.protocol + '//');
            }
            
            // Если URL абсолютный, но без протокола, добавляем текущий протокол
            if (fixed.startsWith('//')) {
                fixed = window.location.protocol + fixed;
            }
            
            return fixed;
        };
        
        // Исправляем baseURL
        if (config.baseURL) {
            config.baseURL = fixUrl(config.baseURL);
        }
        
        // Исправляем URL
        if (config.url) {
            const originalUrl = config.url;
            config.url = fixUrl(config.url);
            
            // Если URL был абсолютным (содержит домен), убеждаемся что он правильный
            if (originalUrl.includes('://') || originalUrl.startsWith('//')) {
                // Это абсолютный URL - baseURL не нужен
                config.baseURL = '';
            }
        }
        
        return config;
    }, (error) => {
        // Обработка ошибок в interceptor
        return Promise.reject(error);
    });
}

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
