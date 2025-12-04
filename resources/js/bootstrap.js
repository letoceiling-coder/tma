import axios from 'axios';

// Создаем экземпляр axios с правильными настройками
const axiosInstance = axios.create({
    baseURL: '',
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
    },
});

// Перехватываем XMLHttpRequest на низком уровне для исправления URL
// Это гарантирует, что все запросы будут исправлены, даже если они идут не через axios
if (typeof window !== 'undefined' && window.XMLHttpRequest) {
    const OriginalXHR = window.XMLHttpRequest;
    
    window.XMLHttpRequest = function() {
        const xhr = new OriginalXHR();
        const originalOpen = xhr.open;
        
        xhr.open = function(method, url, async, user, password) {
            // Исправляем URL перед открытием соединения
            if (typeof url === 'string') {
                const originalUrl = url;
                
                // Убираем /public/ из URL (все вхождения)
                url = url.replace(/\/public\//g, '/');
                url = url.replace(/^\/public/, '');
                url = url.replace(/\/public$/, '');
                
                // Заменяем HTTP на HTTPS
                if (url.startsWith('http://')) {
                    url = url.replace('http://', window.location.protocol + '//');
                }
                
                // Логируем ВСЕ запросы к config/bot для отладки
                if (url.includes('config/bot')) {
                    console.log('[XHR Fix] config/bot запрос:', {
                        original: originalUrl,
                        fixed: url,
                        method: method,
                        changed: originalUrl !== url
                    });
                }
                
                // Если URL был изменен, логируем для отладки
                if (originalUrl !== url && (originalUrl.includes('/public/') || originalUrl.startsWith('http://'))) {
                    console.log('[XHR Fix] URL исправлен:', originalUrl, '->', url);
                }
            }
            
            return originalOpen.call(this, method, url, async, user, password);
        };
        
        return xhr;
    };
    
    // Сохраняем оригинальный конструктор для совместимости
    window.XMLHttpRequest.prototype = OriginalXHR.prototype;
}

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
        
        // Логируем запросы к config/bot для отладки
        if (config.url && config.url.includes('config/bot')) {
            console.log('[Axios Interceptor] config/bot запрос ДО исправления:', {
                originalUrl: config.url,
                baseURL: config.baseURL,
                method: config.method
            });
        }
        
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
