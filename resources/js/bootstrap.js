import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Функция для исправления URL
function fixUrl(url) {
    if (!url) return url;
    
    // Убираем /public/ из URL
    let fixed = url.replace(/\/public\//g, '/').replace(/^\/public/, '');
    
    // Если URL абсолютный и содержит http://, заменяем на https://
    if (fixed.startsWith('http://')) {
        fixed = fixed.replace('http://', 'https://');
    }
    
    return fixed;
}

// Interceptor для исправления URL (удаление /public/ и замена http:// на https://)
window.axios.interceptors.request.use(
    function (config) {
        // Логируем оригинальный URL
        const originalUrl = config.url;
        const originalBaseURL = config.baseURL;
        
        console.log('🔍 Axios Request Interceptor - BEFORE:', {
            url: originalUrl,
            baseURL: originalBaseURL,
            fullURL: (originalBaseURL || '') + (originalUrl || ''),
        });
        
        // Исправляем baseURL если он задан
        if (config.baseURL) {
            config.baseURL = fixUrl(config.baseURL);
        }
        
        // Исправляем URL
        if (config.url) {
            config.url = fixUrl(config.url);
        }
        
        console.log('✅ Axios Request Interceptor - AFTER:', {
            url: config.url,
            baseURL: config.baseURL,
            fullURL: (config.baseURL || '') + (config.url || ''),
        });
        
        return config;
    },
    function (error) {
        console.error('❌ Axios Request Interceptor - Error:', error);
        return Promise.reject(error);
    }
);

// Перехватываем XMLHttpRequest для исправления URL
const originalXHROpen = XMLHttpRequest.prototype.open;
XMLHttpRequest.prototype.open = function(method, url, ...args) {
    const fixedUrl = fixUrl(url);
    if (fixedUrl !== url) {
        console.log('🔧 XMLHttpRequest - Fixed URL:', { original: url, fixed: fixedUrl, method });
    } else {
        // Логируем все запросы к API для отладки
        if (typeof url === 'string' && url.includes('/api/')) {
            console.log('🔍 XMLHttpRequest - API Request:', { method, url, fixedUrl });
        }
    }
    return originalXHROpen.call(this, method, fixedUrl, ...args);
};

// Перехватываем Fetch API для исправления URL
const originalFetch = window.fetch;
window.fetch = function(url, ...args) {
    let fixedUrl = url;
    if (typeof url === 'string') {
        fixedUrl = fixUrl(url);
    } else if (url instanceof Request) {
        fixedUrl = new Request(fixUrl(url.url), url);
    }
    
    if (fixedUrl !== url) {
        console.log('🔧 Fetch API - Fixed URL:', { original: url, fixed: fixedUrl });
    } else if (typeof url === 'string' && url.includes('/api/')) {
        // Логируем все запросы к API для отладки
        console.log('🔍 Fetch API - API Request:', { url, fixedUrl });
    }
    
    return originalFetch.call(this, fixedUrl, ...args);
};
