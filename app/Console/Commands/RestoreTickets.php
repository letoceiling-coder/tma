<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WheelSetting;
use App\Services\TelegramNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RestoreTickets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wow:restore-tickets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Восстановить билеты пользователям (каждые 2-4 часа)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Восстановление билетов...');

        // Интервал восстановления (настраивается в админ панели)
        $settings = WheelSetting::getSettings();
        $restoreInterval = ($settings->ticket_restore_hours ?? config('app.ticket_restore_hours', 3)) * 3600;

        $users = User::whereNotNull('telegram_id')
            ->whereNotNull('last_spin_at') // Только пользователи, которые хотя бы раз крутили
            ->where('tickets_available', '<', 3)
            ->get();

        $restored = 0;
        $notified = 0;

        foreach ($users as $user) {
            // Рассчитываем сколько билетов должно быть восстановлено
            $secondsSinceLastSpin = now()->diffInSeconds($user->last_spin_at);
            
            if ($secondsSinceLastSpin >= $restoreInterval) {
                DB::beginTransaction();
                try {
                    // Рассчитываем количество билетов для восстановления
                    $ticketsToRestore = min(
                        floor($secondsSinceLastSpin / $restoreInterval),
                        3 - $user->tickets_available
                    );
                    
                    if ($ticketsToRestore > 0) {
                        $oldTickets = $user->tickets_available;
                        $user->tickets_available = min($user->tickets_available + $ticketsToRestore, 3);
                        $user->save();

                        // Отправляем уведомление о восстановлении билетов
                        if ($oldTickets < 3 && $user->tickets_available > $oldTickets) {
                            TelegramNotificationService::notifyNewTicket($user);
                            $notified++;
                        }

                        DB::commit();
                        $restored++;
                        $this->line("✓ Восстановлено {$ticketsToRestore} билет(а/ов) пользователю {$user->telegram_id} ({$oldTickets} → {$user->tickets_available})");
                    }
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("✗ Ошибка для пользователя {$user->telegram_id}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Готово! Восстановлено: {$restored}, Уведомлений отправлено: {$notified}");

        return Command::SUCCESS;
    }
}

