<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WheelSetting;
use App\Services\TelegramNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccrueTickets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wow:accrue-tickets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Автоматическое начисление билетов пользователям через заданный интервал';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Проверка автоматического начисления билетов...');

        $settings = WheelSetting::getSettings();

        // Проверяем, включено ли автоматическое начисление
        if (!$settings->ticket_accrual_enabled) {
            $this->info('Автоматическое начисление билетов отключено в настройках.');
            return Command::SUCCESS;
        }

        $intervalHours = $settings->ticket_accrual_interval_hours ?? 24;
        $notificationsEnabled = $settings->ticket_accrual_notifications_enabled ?? true;

        // Находим всех пользователей с telegram_id
        // У которых либо last_ticket_accrual_at === null (новые пользователи)
        // либо прошло >= intervalHours с последнего начисления
        $users = User::whereNotNull('telegram_id')
            ->where(function ($query) use ($intervalHours) {
                $query->whereNull('last_ticket_accrual_at')
                    ->orWhereRaw('TIMESTAMPDIFF(HOUR, last_ticket_accrual_at, NOW()) >= ?', [$intervalHours]);
            })
            ->get();

        $accrued = 0;
        $notified = 0;

        foreach ($users as $user) {
            DB::beginTransaction();
            try {
                $oldTickets = $user->tickets_available;
                $lastAccrual = $user->last_ticket_accrual_at;

                // Проверяем, прошло ли достаточно времени с последнего начисления
                $shouldAccrue = false;
                if (!$lastAccrual) {
                    // Новый пользователь - начисляем сразу
                    $shouldAccrue = true;
                } else {
                    $hoursSinceLastAccrual = now()->diffInHours($lastAccrual);
                    if ($hoursSinceLastAccrual >= $intervalHours) {
                        $shouldAccrue = true;
                    }
                }

                if (!$shouldAccrue) {
                    DB::rollBack();
                    continue;
                }

                // Логируем начисление билета ДО изменения
                Log::info('Accruing ticket automatically (before)', [
                    'user_id' => $user->id,
                    'telegram_id' => $user->telegram_id,
                    'tickets_before' => $oldTickets,
                    'tickets_to_add' => 1,
                    'last_ticket_accrual_at' => $lastAccrual?->toIso8601String(),
                    'interval_hours' => $intervalHours,
                    'current_time' => now()->toIso8601String(),
                ]);

                // Начисляем 1 билет
                $user->tickets_available = $user->tickets_available + 1;
                $user->last_ticket_accrual_at = now();
                $user->save();

                // Логируем начисление билета ПОСЛЕ изменения
                Log::info('Accruing ticket automatically (after)', [
                    'user_id' => $user->id,
                    'telegram_id' => $user->telegram_id,
                    'tickets_before' => $oldTickets,
                    'tickets_after' => $user->tickets_available,
                    'tickets_added' => 1,
                    'last_ticket_accrual_at' => $user->last_ticket_accrual_at->toIso8601String(),
                    'timestamp' => now()->toIso8601String(),
                ]);

                // Создаем запись в user_tickets для отслеживания источника
                \App\Models\UserTicket::create([
                    'user_id' => $user->id,
                    'tickets_count' => 1,
                    'restored_at' => null,
                    'source' => 'automatic_accrual',
                ]);

                // Отправляем уведомление, если включено
                if ($notificationsEnabled && $user->tickets_available > $oldTickets) {
                    $message = "Тебе начислен новый билет! Заходи и используй его.";
                    TelegramNotificationService::sendNotification($user->telegram_id, $message);
                    $notified++;
                }

                DB::commit();
                $accrued++;
                $this->line("✓ Начислен 1 билет пользователю {$user->telegram_id} ({$oldTickets} → {$user->tickets_available})");
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("✗ Ошибка для пользователя {$user->telegram_id}: {$e->getMessage()}");
                Log::error('Error accruing ticket', [
                    'user_id' => $user->id,
                    'telegram_id' => $user->telegram_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("Готово! Начислено билетов: {$accrued}, Уведомлений отправлено: {$notified}");

        return Command::SUCCESS;
    }
}
