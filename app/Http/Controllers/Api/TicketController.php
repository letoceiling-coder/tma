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

            // Получаем настройки для расчета времени
            $settings = \App\Models\WheelSetting::getSettings();
            $restoreIntervalSeconds = ($settings->ticket_restore_hours ?? 3) * 3600;

            // Проверяем, нужно ли восстановить билеты
            $this->checkTicketRestore($user);

            // Рассчитываем время до следующего билета
            $nextTicketAt = null;
            $secondsUntilNextTicket = null;
            
            // Используем tickets_depleted_at - точку когда билеты закончились
            if ($user->tickets_available < 3 && $user->tickets_depleted_at) {
                // Рассчитываем сколько полных интервалов прошло с момента окончания билетов
                $secondsSinceDepletion = now()->diffInSeconds($user->tickets_depleted_at);
                $completedIntervals = floor($secondsSinceDepletion / $restoreIntervalSeconds);
                
                // Рассчитываем время следующего восстановления
                // Это будет tickets_depleted_at + (completedIntervals + 1) * interval
                $nextRestoreTime = $user->tickets_depleted_at->copy()->addSeconds(($completedIntervals + 1) * $restoreIntervalSeconds);
                
                // Время до следующего билета (округляем до целого числа секунд)
                $secondsUntilNextTicket = max(0, (int) $nextRestoreTime->diffInSeconds(now()));
                $nextTicketAt = $nextRestoreTime->toIso8601String();
            }

            return response()->json([
                'tickets_available' => min($user->tickets_available, 3),
                'max_tickets' => 3,
                'last_spin_at' => $user->last_spin_at?->toIso8601String(),
                'restore_interval_hours' => $settings->ticket_restore_hours ?? 3,
                'restore_interval_seconds' => $restoreIntervalSeconds,
                'next_ticket_at' => $nextTicketAt,
                'seconds_until_next_ticket' => $secondsUntilNextTicket,
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

        // Если нет точки отсчета (билеты никогда не заканчивались), не восстанавливаем
        if (!$user->tickets_depleted_at) {
            return;
        }

        // Время восстановления билета (настраивается в админ панели)
        $settings = \App\Models\WheelSetting::getSettings();
        $restoreInterval = ($settings->ticket_restore_hours ?? config('app.ticket_restore_hours', 3)) * 3600;
        
        // Рассчитываем сколько билетов должно быть восстановлено с момента окончания билетов
        $secondsSinceDepletion = now()->diffInSeconds($user->tickets_depleted_at);
        
        if ($secondsSinceDepletion >= $restoreInterval) {
            // Рассчитываем количество билетов для восстановления
            $ticketsToRestore = min(
                floor($secondsSinceDepletion / $restoreInterval),
                3 - $user->tickets_available
            );
            
            if ($ticketsToRestore > 0) {
                $oldTickets = $user->tickets_available;
                $user->tickets_available = min($user->tickets_available + $ticketsToRestore, 3);
                
                // Сбрасываем точку отсчета, так как билеты восстановились
                if ($user->tickets_available > 0) {
                    $user->tickets_depleted_at = null;
                }
                
                $user->save();
                
                // Отправляем уведомление о восстановлении билетов
                if ($oldTickets < 3 && $user->tickets_available > $oldTickets) {
                    TelegramNotificationService::notifyNewTicket($user);
                }
            }
        }
    }
}

