<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TelegramService;
use App\Services\TelegramNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TicketController extends Controller
{
    /**
     * Получить количество доступных билетов
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getTickets(Request $request): JsonResponse
    {
        try {
            $initData = $request->header('X-Telegram-Init-Data') ?? $request->query('initData');
            
            if (!$initData) {
                return response()->json([
                    'tickets_available' => 0,
                    'max_tickets' => 3,
                ]);
            }

            $telegramId = TelegramService::getTelegramId($initData);
            
            if (!$telegramId) {
                return response()->json([
                    'tickets_available' => 0,
                    'max_tickets' => 3,
                ]);
            }

            // Находим или создаем пользователя, если его нет
            $user = User::firstOrCreate(
                ['telegram_id' => $telegramId],
                [
                    'name' => 'Telegram User',
                    'email' => "telegram_{$telegramId}@telegram.local",
                    'password' => bcrypt(str()->random(32)),
                    'tickets_available' => 3, // Начальное количество билетов
                    'stars_balance' => 0,
                    'total_spins' => 0,
                    'total_wins' => 0,
                ]
            );

            // Проверяем, нужно ли восстановить билеты (каждые 2-4 часа)
            $this->checkTicketRestore($user);

            return response()->json([
                'tickets_available' => min($user->tickets_available, 3),
                'max_tickets' => 3,
                'last_spin_at' => $user->last_spin_at,
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting tickets', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'tickets_available' => 0,
                'max_tickets' => 3,
            ]);
        }
    }

    /**
     * Проверка и восстановление билетов
     * 
     * @param User $user
     * @return void
     */
    private function checkTicketRestore(User $user): void
    {
        // Если билетов уже 3, не восстанавливаем
        if ($user->tickets_available >= 3) {
            return;
        }

        // Время восстановления билета (2-4 часа, настраивается)
        $restoreInterval = config('app.ticket_restore_hours', 3) * 3600; // По умолчанию 3 часа
        
            // Если это первая проверка или прошло достаточно времени
            if (!$user->last_spin_at || 
                now()->diffInSeconds($user->last_spin_at) >= $restoreInterval) {
                
                $oldTickets = $user->tickets_available;
                
                // Восстанавливаем билеты до максимума (3)
                $user->tickets_available = min($user->tickets_available + 1, 3);
                $user->save();
                
                // Отправляем уведомление, если билет был восстановлен
                if ($oldTickets < 3 && $user->tickets_available > $oldTickets) {
                    TelegramNotificationService::notifyNewTicket($user);
                }
            }
    }
}

