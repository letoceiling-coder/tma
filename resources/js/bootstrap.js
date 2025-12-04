import axios from 'axios';

// Создаем экземпляр axios с правильными настройками
const axiosInstance = axios.create({
    baseURL: '',
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
    },
});

// Экспортируем экземпляр
window.axios = axiosInstance;

// Устанавливаем baseURL на основе текущего протокола и хоста
// Это предотвращает Mixed Content ошибки (HTTPS страница -> HTTP запрос)
if (typeof window !== 'undefined') {
    // Используем относительный путь, чтобы избежать проблем с Mixed Content
    // axios будет использовать текущий протокол (HTTPS) автоматически
    axiosInstance.defaults.baseURL = '';
    
    // Добавляем interceptor для исправления URL, если они содержат /public/ или используют HTTP
    axiosInstance.interceptors.request.use((config) => {
        // Функция для исправления URL
        const fixUrl = (url) => {
            if (!url || typeof url !== 'string') return url;
            
            let fixed = url;
            
            // Убираем /public/ из URL (все вхождения)
            fixed = fixed.replace(/\/public\//g, '/');
            fixed = fixed.replace(/^\/public/, '');
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
        
        // Сохраняем оригинальные значения для отладки
        const originalBaseURL = config.baseURL;
        const originalUrl = config.url;
        
        // Исправляем baseURL
        if (config.baseURL) {
            config.baseURL = fixUrl(config.baseURL);
        }
        
        // Исправляем URL
        if (config.url) {
            config.url = fixUrl(config.url);
            
            // Если URL был абсолютным (содержит домен), убеждаемся что он правильный
            if (originalUrl && (originalUrl.includes('://') || originalUrl.startsWith('//'))) {
                // Это абсолютный URL - baseURL не нужен
                config.baseURL = '';
            }
        }
        
        // Формируем финальный URL для финальной проверки
        let finalUrl = config.url || '';
        if (config.baseURL && !finalUrl.match(/^https?:\/\//) && !finalUrl.startsWith('//') && !finalUrl.startsWith('/')) {
            finalUrl = (config.baseURL.replace(/\/$/, '') + '/' + finalUrl.replace(/^\//, '')).replace(/\/+/g, '/');
        } else if (config.baseURL && finalUrl.startsWith('/')) {
            finalUrl = (config.baseURL.replace(/\/$/, '') + finalUrl).replace(/\/+/g, '/');
        }
        
        // Финальная проверка и исправление полного URL
        if (finalUrl.includes('/public/') || finalUrl.startsWith('http://')) {
            const beforeFix = finalUrl;
            
            // Убираем /public/
            finalUrl = finalUrl.replace(/\/public\//g, '/');
            
            // Заменяем HTTP на HTTPS
            if (finalUrl.startsWith('http://')) {
                finalUrl = finalUrl.replace('http://', window.location.protocol + '//');
            }
            
            // Обновляем config
            if (config.baseURL && !originalUrl.match(/^https?:\/\//)) {
                // Относительный URL - обновляем только url
                config.url = finalUrl.replace(config.baseURL.replace(/\/$/, ''), '');
            } else {
                // Абсолютный URL - обновляем url и очищаем baseURL
                config.url = finalUrl;
                config.baseURL = '';
            }
        }
        
        // Убеждаемся, что baseURL пустой, если мы используем абсолютный URL
        if (config.url && (config.url.match(/^https?:\/\//) || config.url.startsWith('//'))) {
            config.baseURL = '';
        }
        
        return config;
    }, (error) => {
        // Обработка ошибок в interceptor
        return Promise.reject(error);
    });
}

// Уже установлено при создании экземпляра
