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
        if (config.url) {
            // Убираем /public/ из URL, если он там есть
            if (config.url.includes('/public/')) {
                config.url = config.url.replace('/public/', '/');
            }
            // Убеждаемся, что используется текущий протокол (HTTPS)
            if (config.url.startsWith('http://')) {
                config.url = config.url.replace('http://', window.location.protocol + '//');
            }
            // Если URL абсолютный, но без протокола, добавляем текущий протокол
            if (config.url.startsWith('//')) {
                config.url = window.location.protocol + config.url;
            }
        }
        return config;
    });
}

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
