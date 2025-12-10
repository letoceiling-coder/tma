<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminMenuController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\DeployController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\WheelController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\WowAuthController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\StarExchangeController;
use App\Http\Controllers\Api\v1\FolderController;
use App\Http\Controllers\Api\v1\MediaController;
use Illuminate\Support\Facades\Route;

// Публичные роуты для Telegram WebApp
// Получение списка активных каналов
Route::get('/channels', [SubscriptionController::class, 'getActiveChannels']);

// Проверка подписки на канал
Route::get('/check-subscription/{channelUsername}', [SubscriptionController::class, 'checkSubscription']);
Route::get('/check-all-subscriptions', [SubscriptionController::class, 'checkAllSubscriptions']);

// Конфигурация рулетки (публичный доступ)
Route::get('/wheel-config', [WheelController::class, 'getConfig']);

// Защищенные роуты для Telegram WebApp (требуют initData, но не обязательна валидация в режиме разработки)
Route::middleware(['telegram.initdata'])->group(function () {
    // Инициализация пользователя WOW (создание/обновление при первом запуске)
    Route::post('/user/init', [WowAuthController::class, 'init']);
    
    // Рулетка
    Route::post('/spin', [WheelController::class, 'spin']);
    Route::post('/spin/notify', [\App\Http\Controllers\Api\SpinNotificationController::class, 'notify']);
    
    // Билеты
    Route::get('/user/tickets', [TicketController::class, 'getTickets']);
    
    // Рефералы
    Route::get('/referral/link', [ReferralController::class, 'getLink']);
    Route::post('/referral/register', [ReferralController::class, 'register']);
    Route::get('/referral/stats', [ReferralController::class, 'getStats']);
    Route::post('/referral/popup-shown', [ReferralController::class, 'markPopupShown']);
    
    // Лидерборд
    Route::get('/leaderboard', [LeaderboardController::class, 'index']);
    
    // Telegram Stars Exchange
    Route::post('/stars/exchange', [StarExchangeController::class, 'exchangeStars']); // Простой обмен 50 звезд на 20 билетов
    Route::post('/stars/exchange/initiate', [StarExchangeController::class, 'initiateExchange']);
    Route::post('/stars/exchange/confirm', [StarExchangeController::class, 'confirmExchange']);
    Route::get('/stars/exchange/history', [StarExchangeController::class, 'getHistory']);
});

// Webhook для Telegram Stars Exchange (не требует initData, но защищен токеном)
Route::post('/stars/exchange/webhook', [StarExchangeController::class, 'webhook']);

// Webhook для Telegram Bot (защищен middleware)
Route::post('/telegram/webhook', [\App\Http\Controllers\Api\TelegramWebhookController::class, 'handle'])
    ->middleware('telegram.webhook');
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Защищённые роуты
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    
    // Меню
    Route::get('/admin/menu', [AdminMenuController::class, 'index']);
    
    // Уведомления
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/all', [NotificationController::class, 'all']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    
    // Media API (v1)
    Route::prefix('v1')->group(function () {
        // Folders
        Route::get('folders/tree/all', [FolderController::class, 'tree'])->name('folders.tree');
        Route::post('folders/update-positions', [FolderController::class, 'updatePositions'])->name('folders.update-positions');
        Route::post('folders/{id}/restore', [FolderController::class, 'restore'])->name('folders.restore');
        Route::apiResource('folders', FolderController::class);
        
        // Media
        Route::post('media/{id}/restore', [MediaController::class, 'restore'])->name('media.restore');
        Route::delete('media/trash/empty', [MediaController::class, 'emptyTrash'])->name('media.trash.empty');
        Route::apiResource('media', MediaController::class);
        
        // Admin only routes (Roles and Users management)
        Route::middleware('admin')->group(function () {
            Route::apiResource('roles', RoleController::class);
            Route::apiResource('users', UserController::class);
            
            // Конфигурация бота
            Route::prefix('settings/bot')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\Admin\BotConfigController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Api\Admin\BotConfigController::class, 'store']);
                Route::get('/webhook-info', [\App\Http\Controllers\Api\Admin\BotConfigController::class, 'getWebhookInfo']);
                Route::post('/test-connection', [\App\Http\Controllers\Api\Admin\BotConfigController::class, 'testConnection']);
                Route::post('/set-webhook', [\App\Http\Controllers\Api\Admin\BotConfigController::class, 'setWebhook']);
                Route::post('/delete-webhook', [\App\Http\Controllers\Api\Admin\BotConfigController::class, 'deleteWebhook']);
                Route::get('/menu-button', [\App\Http\Controllers\Api\Admin\BotConfigController::class, 'getMenuButton']);
                Route::post('/menu-button', [\App\Http\Controllers\Api\Admin\BotConfigController::class, 'setMenuButton']);
                Route::delete('/menu-button', [\App\Http\Controllers\Api\Admin\BotConfigController::class, 'removeMenuButton']);
            });
            
            // WOW Рулетка - Админ панель
            Route::prefix('wow')->group(function () {
                // Каналы
                Route::apiResource('channels', \App\Http\Controllers\Api\Admin\ChannelController::class);
                
                // Рулетка
                Route::get('wheel', [\App\Http\Controllers\Api\Admin\WheelController::class, 'index']);
                Route::put('wheel/sectors/{id}', [\App\Http\Controllers\Api\Admin\WheelController::class, 'update']);
                Route::post('wheel/bulk-update', [\App\Http\Controllers\Api\Admin\WheelController::class, 'bulkUpdate']);
                Route::post('wheel/settings', [\App\Http\Controllers\Api\Admin\WheelController::class, 'updateSettings']);
                Route::get('wheel/validate', [\App\Http\Controllers\Api\Admin\WheelController::class, 'validateProbabilities']);
                
                // Выигрыши
                Route::get('wins', [\App\Http\Controllers\Api\Admin\WinsController::class, 'index']);
                Route::get('wins/stats', [\App\Http\Controllers\Api\Admin\WinsController::class, 'stats']);
                
                // Лидерборд (админка)
                Route::get('leaderboard', [\App\Http\Controllers\Api\Admin\LeaderboardAdminController::class, 'index']);
                
                // Пользователи WOW
                Route::get('users', [\App\Http\Controllers\Api\Admin\WowUserController::class, 'index']);
                Route::get('users/{id}', [\App\Http\Controllers\Api\Admin\WowUserController::class, 'show']);
                Route::post('users/{id}/add-tickets', [\App\Http\Controllers\Api\Admin\WowUserController::class, 'addTickets']);
                
                // Призы лидерборда
                Route::get('leaderboard-prizes', [\App\Http\Controllers\Api\Admin\LeaderboardPrizeController::class, 'index']);
                Route::post('leaderboard-prizes', [\App\Http\Controllers\Api\Admin\LeaderboardPrizeController::class, 'store']);
                Route::put('leaderboard-prizes/{id}', [\App\Http\Controllers\Api\Admin\LeaderboardPrizeController::class, 'update']);
                Route::delete('leaderboard-prizes/{id}', [\App\Http\Controllers\Api\Admin\LeaderboardPrizeController::class, 'destroy']);
                Route::post('leaderboard-prizes/bulk-update', [\App\Http\Controllers\Api\Admin\LeaderboardPrizeController::class, 'bulkUpdate']);
                Route::post('leaderboard-prizes/update-period', [\App\Http\Controllers\Api\Admin\LeaderboardPrizeController::class, 'updatePeriod']);
                
                // Типы призов
                Route::apiResource('prize-types', \App\Http\Controllers\Api\Admin\PrizeTypeController::class);
                
                // Настройки приветствия
                Route::get('welcome', [\App\Http\Controllers\Api\Admin\WelcomeController::class, 'index']);
                Route::post('welcome', [\App\Http\Controllers\Api\Admin\WelcomeController::class, 'update']);
            });
        });
    });
});

// Маршрут для деплоя (защищен токеном)
Route::post('/deploy', [DeployController::class, 'deploy'])
    ->middleware('deploy.token');

// Маршрут для выполнения seeders (защищен токеном)
Route::post('/seed', [DeployController::class, 'seed'])
    ->middleware('deploy.token');

// Публичные роуты для просмотра логов
Route::get('/logs', [\App\Http\Controllers\LogController::class, 'getLogs']);
Route::get('/logs/files', [\App\Http\Controllers\LogController::class, 'getLogFilesList']);
Route::post('/logs/clear', [\App\Http\Controllers\LogController::class, 'clearLogs']);

