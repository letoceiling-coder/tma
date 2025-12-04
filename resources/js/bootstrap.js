import axios from 'axios';
window.axios = axios;

// Устанавливаем baseURL на основе текущего протокола и хоста
// Это предотвращает Mixed Content ошибки (HTTPS страница -> HTTP запрос)
if (typeof window !== 'undefined') {
    const protocol = window.location.protocol;
    const host = window.location.host;
    // Используем относительный путь, чтобы избежать проблем с Mixed Content
    // axios будет использовать текущий протокол автоматически
    window.axios.defaults.baseURL = '';
}

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
