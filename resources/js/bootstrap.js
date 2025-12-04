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

// КРИТИЧНО: Исправляем document.baseURI
// document.baseURI может содержать полный путь к странице, а не базовый URL
// Нужно установить правильный базовый URL для разрешения относительных путей
(function() {
    const currentBaseURI = document.baseURI;
    const locationOrigin = window.location.origin;
    const locationPathname = window.location.pathname;
    
    // Определяем правильный базовый URL
    // Если мы на странице /admin/*, базовый URL должен быть /admin/
    // Иначе базовый URL должен быть /
    let basePath = '/';
    if (locationPathname.startsWith('/admin')) {
        basePath = '/admin/';
    }
    
    const baseURI = locationOrigin + basePath;
    
    // Убираем /public/ если есть
    const fixedBaseURI = fixUrl(baseURI);
    
    console.log('🔧 Fixing document.baseURI:', { 
        original: currentBaseURI, 
        fixed: fixedBaseURI,
        locationOrigin: locationOrigin,
        locationPathname: locationPathname,
        basePath: basePath,
    });
    
    // Пытаемся переопределить document.baseURI
    try {
        Object.defineProperty(document, 'baseURI', {
            get: function() {
                return fixedBaseURI;
            },
            configurable: true
        });
        console.log('✅ Successfully overridden document.baseURI');
    } catch (e) {
        console.warn('⚠️ Cannot override document.baseURI:', e);
        // Если не удалось переопределить, создаем/исправляем <base> тег
        let baseTag = document.querySelector('base');
        if (baseTag) {
            baseTag.href = fixedBaseURI;
            console.log('✅ Fixed <base> tag href:', fixedBaseURI);
        } else {
            // Создаем <base> тег если его нет
            baseTag = document.createElement('base');
            baseTag.href = fixedBaseURI;
            document.head.insertBefore(baseTag, document.head.firstChild);
            console.log('✅ Created <base> tag with href:', fixedBaseURI);
        }
    }
})();

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
    let fixedUrl = url;
    
    // Если URL абсолютный и содержит /public/ или http://, исправляем его
    if (typeof url === 'string') {
        // Проверяем, является ли URL абсолютным
        if (url.startsWith('http://') || url.startsWith('https://')) {
            // Абсолютный URL - исправляем его
            fixedUrl = fixUrl(url);
        } else {
            // Относительный URL - исправляем на всякий случай
            fixedUrl = fixUrl(url);
        }
    }
    
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
        
        // Если responseURL содержит /public/ или http://, это проблема
        if (this.responseURL && (this.responseURL.includes('/public/') || this.responseURL.startsWith('http://'))) {
            console.error('❌ XMLHttpRequest.send - URL still contains /public/ or http://:', this.responseURL);
        }
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
