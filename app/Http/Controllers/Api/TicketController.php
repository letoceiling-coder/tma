<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TelegramService;
use App\Services\TelegramNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
                ]);
            }

            $telegramId = TelegramService::getTelegramId($initData);
            
            if (!$telegramId) {
                return response()->json([
                    'tickets_available' => 0,
                ]);
            }

            // Проверяем наличие колонки telegram_id в таблице users
            if (!Schema::hasColumn('users', 'telegram_id')) {
                Log::error('Column telegram_id not found in users table. Please run migrations.');
                return response()->json([
                    'error' => 'Database migration required',
                    'message' => 'Колонка telegram_id отсутствует в таблице users. Выполните миграции: php artisan migrate',
                    'tickets_available' => 0,
                ], 500);
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
                // Используем разницу timestamp для правильного расчета (если restore_time в будущем - положительное, если в прошлом - 0)
                $secondsUntilNextTicket = max(0, $restoreTime->timestamp - now()->timestamp);
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
                'tickets_available' => $user->tickets_available,
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
            ]);
        }
    }

    /**
     * Проверка и восстановление билетов
     * 
     * ЛОГИКА:
     * - tickets_depleted_at хранит момент, когда билеты закончились (стали 0)
     * - От этого момента через интервал (ticket_restore_hours) восстанавливается 1 билет
     * - Если билеты снова становятся 0, снова фиксируется момент и через интервал добавляется еще 1 билет
     * - Процесс продолжается без ограничений
     * 
     * @param User $user
     * @return void
     */
    private function checkTicketRestore(User $user): void
    {
        // Если билетов больше 0, сбрасываем точку восстановления
        if ($user->tickets_available > 0) {
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
            $user->tickets_available = $user->tickets_available + 1;
            
            Log::info('Restoring ticket', [
                'user_id' => $user->id,
                'old_tickets' => $oldTickets,
                'new_tickets' => $user->tickets_available,
            ]);
            
            // Если билеты > 0, сбрасываем точку восстановления
            // Новая точка отсчета установится только когда билеты снова станут 0
            if ($user->tickets_available > 0) {
                $user->tickets_depleted_at = null;
            } else {
                // Если билеты все еще 0, обновляем точку отсчета на текущий момент
                // Это нужно для восстановления следующего билета через интервал
                $user->tickets_depleted_at = now();
                Log::info('Ticket restored but still 0, resetting tickets_depleted_at', [
                    'user_id' => $user->id,
                    'new_tickets_depleted_at' => $user->tickets_depleted_at->toIso8601String(),
                ]);
            }
            
            $user->save();
            
            // Отправляем уведомление о восстановлении билета
            if ($user->tickets_available > $oldTickets) {
                TelegramNotificationService::notifyNewTicket($user);
            }
        }
    }
}

