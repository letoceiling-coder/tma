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

// Исправляем document.baseURI если он содержит /public/
if (document.baseURI && document.baseURI.includes('/public/')) {
    const fixedBaseURI = fixUrl(document.baseURI);
    console.log('🔧 Fixing document.baseURI:', { original: document.baseURI, fixed: fixedBaseURI });
    // К сожалению, document.baseURI только для чтения, но мы можем перехватить его через Object.defineProperty
    try {
        Object.defineProperty(document, 'baseURI', {
            get: function() {
                return fixedBaseURI;
            },
            configurable: true
        });
    } catch (e) {
        console.warn('⚠️ Cannot override document.baseURI:', e);
    }
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
            documentBaseURI: document.baseURI,
            locationHref: window.location.href,
        });
        
        // Исправляем baseURL если он задан
        if (config.baseURL) {
            config.baseURL = fixUrl(config.baseURL);
        }
        
        // Исправляем URL
        if (config.url) {
            config.url = fixUrl(config.url);
        }
        
        // Убеждаемся, что URL не содержит /public/ и использует https://
        // Если URL все еще содержит /public/, исправляем его еще раз
        if (config.url && config.url.includes('/public/')) {
            console.warn('⚠️ URL still contains /public/ after fixUrl, fixing again:', config.url);
            config.url = fixUrl(config.url);
        }
        
        // Если baseURL содержит /public/, исправляем его еще раз
        if (config.baseURL && config.baseURL.includes('/public/')) {
            console.warn('⚠️ baseURL still contains /public/ after fixUrl, fixing again:', config.baseURL);
            config.baseURL = fixUrl(config.baseURL);
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
const xhrUrlMap = new WeakMap();

XMLHttpRequest.prototype.open = function(method, url, ...args) {
    const fixedUrl = fixUrl(url);
    // Сохраняем оригинальный и исправленный URL для логирования в send
    xhrUrlMap.set(this, { original: url, fixed: fixedUrl, method });
    
    if (fixedUrl !== url) {
        console.log('🔧 XMLHttpRequest.open - Fixed URL:', { original: url, fixed: fixedUrl, method });
    } else {
        // Логируем все запросы к API для отладки
        if (typeof url === 'string' && url.includes('/api/')) {
            console.log('🔍 XMLHttpRequest.open - API Request:', { method, url, fixedUrl });
        }
    }
    return originalXHROpen.call(this, method, fixedUrl, ...args);
};

// Перехватываем send для проверки финального URL
const originalXHRSend = XMLHttpRequest.prototype.send;
XMLHttpRequest.prototype.send = function(...args) {
    const urlInfo = xhrUrlMap.get(this);
    if (urlInfo && urlInfo.fixed.includes('/api/')) {
        // Проверяем, какой URL будет использован
        const currentUrl = this.responseURL || urlInfo.fixed;
        console.log('📤 XMLHttpRequest.send - Sending request:', {
            method: urlInfo.method,
            originalUrl: urlInfo.original,
            fixedUrl: urlInfo.fixed,
            responseURL: this.responseURL,
            currentUrl: currentUrl,
        });
    }
    return originalXHRSend.apply(this, args);
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
