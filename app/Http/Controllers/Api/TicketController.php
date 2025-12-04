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

            // ВАЖНО: Если у пользователя 0 билетов, но tickets_depleted_at не установлен,
            // устанавливаем его на текущий момент (для запуска таймера)
            if ($user->tickets_available === 0 && !$user->tickets_depleted_at) {
                $user->tickets_depleted_at = now();
                $user->save();
                Log::info('Ticket timer started', [
                    'user_id' => $user->id,
                    'telegram_id' => $user->telegram_id,
                    'tickets_depleted_at' => $user->tickets_depleted_at->toIso8601String(),
                ]);
            }

            // Проверяем, нужно ли восстановить билеты
            // ВАЖНО: Вызываем ДО расчета времени, чтобы билеты были восстановлены, если время пришло
            $this->checkTicketRestore($user);
            
            // Обновляем данные пользователя после возможного восстановления
            $user->refresh();

            // Рассчитываем время до следующего билета
            $nextTicketAt = null;
            $secondsUntilNextTicket = null;
            
            // НОВАЯ ЛОГИКА: tickets_depleted_at хранит момент, когда билеты закончились
            // Время до восстановления = tickets_depleted_at + интервал - now()
            // Таймер показывается только если билетов нет (0)
            if ($user->tickets_available === 0 && $user->tickets_depleted_at) {
                $restoreTime = $user->tickets_depleted_at->copy()->addSeconds($restoreIntervalSeconds);
                $secondsUntilNextTicket = max(0, (int) $restoreTime->diffInSeconds(now()));
                $nextTicketAt = $restoreTime->toIso8601String();
                
                Log::debug('Ticket timer calculated', [
                    'user_id' => $user->id,
                    'tickets_available' => $user->tickets_available,
                    'tickets_depleted_at' => $user->tickets_depleted_at->toIso8601String(),
                    'restore_interval_seconds' => $restoreIntervalSeconds,
                    'restore_time' => $restoreTime->toIso8601String(),
                    'seconds_until_next_ticket' => $secondsUntilNextTicket,
                    'now' => now()->toIso8601String(),
                ]);
            } elseif ($user->tickets_available === 0 && !$user->tickets_depleted_at) {
                // Если билетов 0, но tickets_depleted_at не установлен, это не должно происходить
                // но на всякий случай логируем
                Log::warning('User has 0 tickets but tickets_depleted_at is not set', [
                    'user_id' => $user->id,
                    'tickets_available' => $user->tickets_available,
                ]);
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
     * НОВАЯ ЛОГИКА:
     * - tickets_depleted_at хранит момент, когда билеты закончились (стали 0)
     * - От этого момента через интервал (ticket_restore_hours) восстанавливается 1 билет
     * - Если билеты снова становятся 0, снова фиксируется момент и через интервал добавляется еще 1 билет
     * - Процесс продолжается, пока не достигнут максимум (3 билета)
     * 
     * @param User $user
     * @return void
     */
    private function checkTicketRestore(User $user): void
    {
        // Если билетов уже 3, не восстанавливаем
        if ($user->tickets_available >= 3) {
            // Сбрасываем точку восстановления, если она была установлена
            if ($user->tickets_depleted_at) {
                $user->tickets_depleted_at = null;
                $user->save();
            }
            return;
        }

        // Если нет точки восстановления, не восстанавливаем
        if (!$user->tickets_depleted_at) {
            return;
        }

        // Получаем интервал восстановления из настроек
        $settings = \App\Models\WheelSetting::getSettings();
        $restoreIntervalSeconds = ($settings->ticket_restore_hours ?? 3) * 3600;
        
        // Вычисляем момент восстановления: tickets_depleted_at + интервал
        $restoreTime = $user->tickets_depleted_at->copy()->addSeconds($restoreIntervalSeconds);
        
        Log::debug('Checking ticket restore', [
            'user_id' => $user->id,
            'tickets_available' => $user->tickets_available,
            'tickets_depleted_at' => $user->tickets_depleted_at->toIso8601String(),
            'restore_interval_seconds' => $restoreIntervalSeconds,
            'restore_time' => $restoreTime->toIso8601String(),
            'now' => now()->toIso8601String(),
            'should_restore' => now() >= $restoreTime,
        ]);
        
        // Если текущее время >= момента восстановления → билет готов
        if (now() >= $restoreTime) {
            $oldTickets = $user->tickets_available;
            $user->tickets_available = min($user->tickets_available + 1, 3);
            
            Log::info('Restoring ticket', [
                'user_id' => $user->id,
                'old_tickets' => $oldTickets,
                'new_tickets' => $user->tickets_available,
            ]);
            
            // Если билетов стало 3, сбрасываем точку восстановления
            if ($user->tickets_available >= 3) {
                $user->tickets_depleted_at = null;
            } else {
                // Если билеты все еще меньше 3, обновляем точку отсчета на текущий момент
                // Это нужно для восстановления следующего билета через интервал
                // Но только если билеты сейчас 0 (иначе точка отсчета не нужна, пока билеты не закончатся)
                if ($user->tickets_available === 0) {
                    $user->tickets_depleted_at = now();
                    Log::info('Ticket restored but still 0, resetting tickets_depleted_at', [
                        'user_id' => $user->id,
                        'new_tickets_depleted_at' => $user->tickets_depleted_at->toIso8601String(),
                    ]);
                } else {
                    // Если билеты > 0, но < 3, сбрасываем точку отсчета
                    // Новая точка отсчета установится только когда билеты снова станут 0
                    $user->tickets_depleted_at = null;
                }
            }
            
            $user->save();
            
            // Отправляем уведомление о восстановлении билета
            if ($oldTickets < 3 && $user->tickets_available > $oldTickets) {
                TelegramNotificationService::notifyNewTicket($user);
            }
        }
    }
}

