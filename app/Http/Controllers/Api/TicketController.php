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

            // Получаем настройки для определения количества стартовых билетов
            $settings = \App\Models\WheelSetting::getSettings();
            $initialTicketsCount = $settings->getValidStartTickets(); // Валидированное значение (по умолчанию 1)

            // Находим или создаем пользователя, если его нет
            $user = User::firstOrCreate(
                ['telegram_id' => $telegramId],
                [
                    'name' => 'Telegram User',
                    'email' => "telegram_{$telegramId}@telegram.local",
                    'password' => bcrypt(str()->random(32)),
                    'tickets_available' => $initialTicketsCount, // Используем настройку из админки
                    'stars_balance' => 0,
                    'total_spins' => 0,
                    'total_wins' => 0,
                ]
            );

            // Если это новый пользователь, создаем запись в user_tickets для отслеживания источника
            if ($user->wasRecentlyCreated) {
                \App\Models\UserTicket::create([
                    'user_id' => $user->id,
                    'tickets_count' => $initialTicketsCount,
                    'restored_at' => null, // Стартовые билеты доступны сразу
                    'source' => 'initial_bonus',
                ]);
                
                Log::info('Initial tickets granted to new user (from getTickets)', [
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                    'initial_tickets_count' => $initialTicketsCount,
                ]);
            }

            // Получаем настройки для расчета времени
            $settings = \App\Models\WheelSetting::getSettings();
            $restoreIntervalSeconds = ($settings->ticket_restore_hours ?? 3) * 3600;

            // Проверяем и начисляем ежедневные билеты (ДО проверки восстановления)
            // Это должно работать независимо от текущего баланса
            $this->checkDailyTickets($user);
            
            // Обновляем данные пользователя после возможного начисления ежедневных билетов
            $user->refresh();

            // ВАЖНО: Если у пользователя 0 билетов, но tickets_depleted_at не установлен,
            // устанавливаем его на текущий момент (для запуска таймера)
            // НО: если referral_popup_shown_at уже установлен, это означает, что билеты уже были 0 ранее
            // и pop-up уже был показан, поэтому не устанавливаем tickets_depleted_at заново
            if ($user->tickets_available === 0 && !$user->tickets_depleted_at && !$user->referral_popup_shown_at) {
                // Это первый раз, когда билеты стали 0 - устанавливаем точку отсчета
                $user->tickets_depleted_at = now();
                $user->save();
                Log::info('Ticket timer started', [
                    'user_id' => $user->id,
                    'telegram_id' => $user->telegram_id,
                    'tickets_depleted_at' => $user->tickets_depleted_at->toIso8601String(),
                ]);
            } elseif ($user->tickets_available === 0 && !$user->tickets_depleted_at && $user->referral_popup_shown_at) {
                // Билеты 0, pop-up уже был показан, но tickets_depleted_at не установлен
                // Это означает, что билеты были восстановлены и снова стали 0
                // Устанавливаем новую точку отсчета
                $user->tickets_depleted_at = now();
                // Сбрасываем флаг показа pop-up, чтобы он мог появиться снова
                $user->referral_popup_shown_at = null;
                $user->save();
                Log::info('Ticket timer restarted after new depletion', [
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
                Log::channel('wheel-errors')->warning('User has 0 tickets but tickets_depleted_at is not set', [
                    'telegram_id' => $user->telegram_id,
                    'user_id' => $user->id,
                    'tickets_available' => $user->tickets_available,
                ]);
                Log::warning('User has 0 tickets but tickets_depleted_at is not set', [
                    'user_id' => $user->id,
                    'tickets_available' => $user->tickets_available,
                ]);
            }

            // Проверяем, нужно ли показать pop-up о приглашении друга
            // Pop-up показывается только если:
            // 1. Билеты = 0
            // 2. Pop-up еще не был показан в этом цикле (referral_popup_shown_at === null)
            $shouldShowReferralPopup = $user->tickets_available === 0 && !$user->referral_popup_shown_at;

            return response()->json([
                'tickets_available' => $user->tickets_available,
                'last_spin_at' => $user->last_spin_at?->toIso8601String(),
                'restore_interval_hours' => $settings->ticket_restore_hours ?? 3,
                'restore_interval_seconds' => $restoreIntervalSeconds,
                'next_ticket_at' => $nextTicketAt,
                'seconds_until_next_ticket' => $secondsUntilNextTicket,
                'should_show_referral_popup' => $shouldShowReferralPopup,
            ]);

        } catch (\Exception $e) {
            // Логируем в отдельный файл для ошибок пользовательской части
            Log::channel('wheel-errors')->error('Error getting tickets', [
                'telegram_id' => $telegramId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => [
                    'init_data_provided' => !empty($initData),
                ],
            ]);
            Log::error('Error getting tickets', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'tickets_available' => 0,
            ]);
        }
    }

    /**
     * Проверка и начисление ежедневных билетов
     * 
     * ЛОГИКА:
     * - Если last_ticket_received_at === null (новый пользователь) и билетов 0 → начислить default_daily_tickets
     * - Если прошло >= 24 часов с last_ticket_received_at → начислить daily_tickets
     * - Работает независимо от текущего баланса билетов
     * 
     * @param User $user
     * @return void
     */
    private function checkDailyTickets(User $user): void
    {
        $settings = \App\Models\WheelSetting::getSettings();
        $dailyTickets = $settings->daily_tickets ?? 1;
        $defaultTickets = $settings->default_daily_tickets ?? 1;
        
        $shouldAllocate = false;
        $ticketsToAllocate = 0;
        
        // Если это первый раз (last_ticket_received_at === null)
        if (!$user->last_ticket_received_at) {
            // При первом входе, если у пользователя 0 билетов, начисляем default_daily_tickets
            if ($user->tickets_available === 0) {
                $shouldAllocate = true;
                $ticketsToAllocate = $defaultTickets;
                
                Log::info('First login: allocating default daily tickets', [
                    'user_id' => $user->id,
                    'telegram_id' => $user->telegram_id,
                    'tickets_before' => $user->tickets_available,
                    'tickets_to_allocate' => $ticketsToAllocate,
                ]);
            }
        } else {
            // Проверяем, прошло ли 24 часа с последнего начисления
            // Ежедневные билеты начисляются независимо от текущего баланса
            $hoursSinceLastTicket = now()->diffInHours($user->last_ticket_received_at);
            
            if ($hoursSinceLastTicket >= 24) {
                $shouldAllocate = true;
                $ticketsToAllocate = $dailyTickets;
                
                Log::info('24 hours passed: allocating daily tickets', [
                    'user_id' => $user->id,
                    'telegram_id' => $user->telegram_id,
                    'tickets_before' => $user->tickets_available,
                    'tickets_to_allocate' => $ticketsToAllocate,
                    'hours_since_last' => $hoursSinceLastTicket,
                    'last_ticket_received_at' => $user->last_ticket_received_at->toIso8601String(),
                ]);
            }
        }
        
        if ($shouldAllocate && $ticketsToAllocate > 0) {
            $oldTickets = $user->tickets_available;
            $wasFirstTime = !$user->last_ticket_received_at;
            $user->tickets_available = $user->tickets_available + $ticketsToAllocate;
            $user->last_ticket_received_at = now();
            
            // Если билеты стали больше 0, сбрасываем точку восстановления
            if ($user->tickets_available > 0 && $user->tickets_depleted_at) {
                $user->tickets_depleted_at = null;
                // Сбрасываем флаг показа pop-up
                if ($user->referral_popup_shown_at) {
                    $user->referral_popup_shown_at = null;
                }
            }
            
            $user->save();
            
            // Создаем запись в user_tickets для отслеживания источника
            $source = $wasFirstTime ? 'default_daily_bonus' : 'daily_bonus';
            \App\Models\UserTicket::create([
                'user_id' => $user->id,
                'tickets_count' => $ticketsToAllocate,
                'restored_at' => null, // Ежедневные билеты доступны сразу
                'source' => $source,
            ]);
            
            Log::info('Daily tickets allocated', [
                'user_id' => $user->id,
                'telegram_id' => $user->telegram_id,
                'tickets_before' => $oldTickets,
                'tickets_after' => $user->tickets_available,
                'tickets_allocated' => $ticketsToAllocate,
                'last_ticket_received_at' => $user->last_ticket_received_at->toIso8601String(),
            ]);
            
            // Отправляем уведомление о начислении билетов
            if ($user->tickets_available > $oldTickets) {
                TelegramNotificationService::notifyNewTicket($user);
            }
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
        // Если билетов больше 0, сбрасываем точку восстановления и флаг показа pop-up
        if ($user->tickets_available > 0) {
            if ($user->tickets_depleted_at) {
                $user->tickets_depleted_at = null;
            }
            // Сбрасываем флаг показа pop-up, чтобы он мог появиться снова при следующем обнулении
            if ($user->referral_popup_shown_at) {
                $user->referral_popup_shown_at = null;
            }
            if ($user->tickets_depleted_at !== $user->getOriginal('tickets_depleted_at') || 
                $user->referral_popup_shown_at !== $user->getOriginal('referral_popup_shown_at')) {
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
            
            // Логируем восстановление билета ДО изменения
            Log::info('Restoring ticket (before)', [
                'user_id' => $user->id,
                'telegram_id' => $user->telegram_id,
                'tickets_before' => $oldTickets,
                'tickets_to_add' => 1,
                'restore_time' => $restoreTime->toIso8601String(),
                'current_time' => now()->toIso8601String(),
            ]);
            
            $user->tickets_available = $user->tickets_available + 1;
            
            // Логируем восстановление билета ПОСЛЕ изменения
            Log::info('Restoring ticket (after)', [
                'user_id' => $user->id,
                'telegram_id' => $user->telegram_id,
                'tickets_before' => $oldTickets,
                'tickets_after' => $user->tickets_available,
                'tickets_added' => 1,
                'timestamp' => now()->toIso8601String(),
            ]);
            
            // Если билеты > 0, сбрасываем точку восстановления и флаг показа pop-up
            // Новая точка отсчета установится только когда билеты снова станут 0
            if ($user->tickets_available > 0) {
                $user->tickets_depleted_at = null;
                // Сбрасываем флаг показа pop-up, чтобы он мог появиться снова при следующем обнулении
                $user->referral_popup_shown_at = null;
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

