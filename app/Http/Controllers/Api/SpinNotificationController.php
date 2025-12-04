<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Spin;
use App\Models\User;
use App\Services\TelegramService;
use App\Services\TelegramNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SpinNotificationController extends Controller
{
    /**
     * Отправить уведомление о результате прокрута
     * Вызывается с фронтенда после завершения анимации
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function notify(Request $request): JsonResponse
    {
        try {
            $initData = $request->header('X-Telegram-Init-Data') ?? $request->query('initData');
            
            if (!$initData) {
                return response()->json([
                    'error' => 'Init data not provided'
                ], 401);
            }

            $telegramId = TelegramService::getTelegramId($initData);
            
            if (!$telegramId) {
                return response()->json([
                    'error' => 'User ID not found'
                ], 401);
            }

            // Находим пользователя
            $user = User::where('telegram_id', $telegramId)->first();
            
            if (!$user) {
                return response()->json([
                    'error' => 'User not found'
                ], 404);
            }

            // Получаем ID прокрута из запроса
            $spinId = $request->input('spin_id');
            
            if (!$spinId) {
                return response()->json([
                    'error' => 'Spin ID not provided'
                ], 400);
            }

            // Находим прокрут
            $spin = Spin::where('id', $spinId)
                ->where('user_id', $user->id)
                ->first();
            
            if (!$spin) {
                return response()->json([
                    'error' => 'Spin not found'
                ], 404);
            }

            // Отправляем уведомление в зависимости от типа приза
            $notificationSent = false;
            
            if ($spin->prize_type === 'money' && $spin->prize_value > 0) {
                $notificationSent = TelegramNotificationService::notifyWin(
                    $user,
                    $spin->prize_value,
                    'money'
                );
            } elseif ($spin->prize_type === 'ticket') {
                $notificationSent = TelegramNotificationService::notifyWin(
                    $user,
                    $spin->prize_value,
                    'ticket'
                );
            } elseif ($spin->prize_type === 'secret_box') {
                $notificationSent = TelegramNotificationService::notifyWin(
                    $user,
                    0,
                    'secret_box'
                );
            }

            return response()->json([
                'success' => true,
                'notification_sent' => $notificationSent,
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending spin notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Произошла ошибка при отправке уведомления'
            ], 500);
        }
    }
}

