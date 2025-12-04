<?php

namespace App\Console\Commands;

use App\Models\User;
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

        // Интервал восстановления (по умолчанию 3 часа)
        $restoreInterval = config('app.ticket_restore_hours', 3) * 3600;

        $users = User::whereNotNull('telegram_id')
            ->where('tickets_available', '<', 3)
            ->get();

        $restored = 0;
        $notified = 0;

        foreach ($users as $user) {
            // Проверяем, прошло ли достаточно времени с последнего прокрута
            if (!$user->last_spin_at || 
                now()->diffInSeconds($user->last_spin_at) >= $restoreInterval) {
                
                DB::beginTransaction();
                try {
                    $oldTickets = $user->tickets_available;
                    $user->tickets_available = min($user->tickets_available + 1, 3);
                    $user->save();

                    // Если билет был восстановлен (было меньше 3), отправляем уведомление
                    if ($oldTickets < 3 && $user->tickets_available > $oldTickets) {
                        TelegramNotificationService::notifyNewTicket($user);
                        $notified++;
                    }

                    DB::commit();
                    $restored++;
                    $this->line("✓ Билет восстановлен пользователю {$user->telegram_id} ({$oldTickets} → {$user->tickets_available})");
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

