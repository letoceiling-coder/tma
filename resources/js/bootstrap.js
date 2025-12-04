import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// КРИТИЧНО: Перехватываем адаптер axios для исправления URL на самом низком уровне
// Axios использует адаптер для выполнения запросов, и там может формироваться абсолютный URL
const originalGetAdapter = axios.getAdapter;
if (originalGetAdapter) {
    axios.getAdapter = function(adapters) {
        const adapter = originalGetAdapter.call(this, adapters);
        
        // Если адаптер - функция, оборачиваем её
        if (typeof adapter === 'function') {
            return function(config) {
                // Исправляем URL перед передачей в адаптер
                if (config.url) {
                    config.url = fixUrl(config.url);
                }
                if (config.baseURL) {
                    config.baseURL = fixUrl(config.baseURL);
                }
                
                // Формируем полный URL для проверки
                const fullUrl = (config.baseURL || '') + (config.url || '');
                if (fullUrl.includes('/public/') || fullUrl.startsWith('http://')) {
                    console.warn('⚠️ Axios Adapter - URL contains /public/ or http://, fixing:', fullUrl);
                    // Исправляем полный URL
                    const fixedFullUrl = fixUrl(fullUrl);
                    // Разделяем обратно на baseURL и url
                    if (fixedFullUrl.startsWith('http://') || fixedFullUrl.startsWith('https://')) {
                        const urlObj = new URL(fixedFullUrl);
                        config.baseURL = urlObj.origin + urlObj.pathname.substring(0, urlObj.pathname.lastIndexOf('/') + 1);
                        config.url = urlObj.pathname.substring(urlObj.pathname.lastIndexOf('/') + 1) + urlObj.search;
                    }
                }
                
                return adapter(config);
            };
        }
        
        return adapter;
    };
}

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
        
        // КРИТИЧНО: Исправляем baseURL если он задан
        if (config.baseURL) {
            config.baseURL = fixUrl(config.baseURL);
        }
        
        // КРИТИЧНО: Исправляем URL
        if (config.url) {
            config.url = fixUrl(config.url);
        }
        
        // КРИТИЧНО: Если URL абсолютный и содержит /public/ или http://, исправляем его
        // Это может произойти, если axios адаптер формирует абсолютный URL
        if (config.url && (config.url.startsWith('http://') || config.url.startsWith('https://'))) {
            const fixedAbsoluteUrl = fixUrl(config.url);
            if (fixedAbsoluteUrl !== config.url) {
                console.warn('⚠️ Axios - Fixed absolute URL:', { original: config.url, fixed: fixedAbsoluteUrl });
                config.url = fixedAbsoluteUrl;
            }
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
        
        // КРИТИЧНО: Убеждаемся, что адаптер не будет формировать абсолютный URL с /public/
        // Переопределяем метод адаптера, если он есть
        if (config.adapter && typeof config.adapter === 'function') {
            const originalAdapter = config.adapter;
            config.adapter = function(config) {
                // Исправляем URL перед передачей в адаптер
                if (config.url) {
                    config.url = fixUrl(config.url);
                }
                if (config.baseURL) {
                    config.baseURL = fixUrl(config.baseURL);
                }
                return originalAdapter(config);
            };
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
    
    // КРИТИЧНО: Исправляем URL независимо от того, абсолютный он или относительный
    if (typeof url === 'string') {
        // Если URL абсолютный и содержит /public/ или http://, исправляем его
        if (url.startsWith('http://') || url.startsWith('https://')) {
            fixedUrl = fixUrl(url);
            console.log('🔧 XMLHttpRequest.open - Fixed absolute URL:', { original: url, fixed: fixedUrl, method });
        } else {
            // Относительный URL - убеждаемся, что он не содержит /public/
            fixedUrl = fixUrl(url);
            if (fixedUrl !== url) {
                console.log('🔧 XMLHttpRequest.open - Fixed relative URL:', { original: url, fixed: fixedUrl, method });
            }
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
    
    // КРИТИЧНО: Перехватываем также setRequestHeader и другие методы, которые могут изменить URL
    // Но самое главное - вызываем open с исправленным URL
    return originalXHROpen.call(this, method, fixedUrl, ...args);
};

// Перехватываем send для проверки финального URL
const originalXHRSend = XMLHttpRequest.prototype.send;
XMLHttpRequest.prototype.send = function(...args) {
    const urlInfo = xhrUrlMap.get(this);
    
    // КРИТИЧНО: Перехватываем событие loadstart для проверки финального URL
    // Это событие срабатывает когда запрос начинает отправляться
    this.addEventListener('loadstart', function(event) {
        if (urlInfo && urlInfo.fixed.includes('/api/')) {
            const finalUrl = this.responseURL || urlInfo.fixed;
            console.log('📡 XMLHttpRequest.loadstart - Final URL:', {
                method: urlInfo.method,
                finalUrl: finalUrl,
                responseURL: this.responseURL,
                originalUrl: urlInfo.original,
                fixedUrl: urlInfo.fixed,
            });
            
            // Если responseURL содержит /public/ или http://, это проблема
            if (finalUrl && (finalUrl.includes('/public/') || finalUrl.startsWith('http://'))) {
                console.error('❌ XMLHttpRequest.loadstart - URL still contains /public/ or http://:', finalUrl);
                console.error('❌ This means the URL was formed AFTER our interceptors!');
            }
        }
    }, { once: true });
    
    if (urlInfo && urlInfo.fixed.includes('/api/')) {
        // Проверяем, какой URL будет использован
        const currentUrl = this.responseURL || urlInfo.fixed;
        console.log('📤 XMLHttpRequest.send - Sending request:', {
            method: urlInfo.method,
            originalUrl: urlInfo.original,
            fixedUrl: urlInfo.fixed,
            responseURL: this.responseURL || '(not available yet)',
            currentUrl: currentUrl,
        });
    }
    
    return originalXHRSend.apply(this, args);
};

// КРИТИЧНО: Перехватываем конструктор URL для исправления URL при их создании
// Это может использоваться axios адаптером для разрешения относительных путей
const OriginalURL = window.URL;
window.URL = function(url, base) {
    // Если base содержит /public/ или http://, исправляем его
    if (base) {
        base = fixUrl(base);
    }
    
    // Если url содержит /public/ или http://, исправляем его
    if (url) {
        url = fixUrl(url);
    }
    
    // Если base не указан, но url относительный, используем правильный baseURI
    if (!base && url && !url.startsWith('http://') && !url.startsWith('https://') && !url.startsWith('/')) {
        // Относительный URL без base - используем исправленный document.baseURI
        base = document.baseURI;
    }
    
    try {
        return new OriginalURL(url, base);
    } catch (e) {
        console.error('❌ URL constructor error:', { url, base, error: e });
        // Если ошибка, пробуем без base
        return new OriginalURL(url);
    }
};
// Копируем статические методы
window.URL.createObjectURL = OriginalURL.createObjectURL;
window.URL.revokeObjectURL = OriginalURL.revokeObjectURL;

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
