import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

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
            // Убираем /public/ из baseURL
            config.baseURL = config.baseURL.replace(/\/public\/?/g, '/').replace(/\/$/, '');
            // Заменяем http:// на https://
            if (config.baseURL.startsWith('http://')) {
                config.baseURL = config.baseURL.replace('http://', 'https://');
            }
        }
        
        // Исправляем URL
        if (config.url) {
            // Убираем /public/ из URL
            config.url = config.url.replace(/^\/public/, '').replace(/\/public\//g, '/');
            
            // Если URL абсолютный и содержит http://, заменяем на https://
            if (config.url.startsWith('http://')) {
                config.url = config.url.replace('http://', 'https://');
            }
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
