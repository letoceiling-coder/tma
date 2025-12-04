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
            ->whereNotNull('tickets_depleted_at') // Только пользователи с установленной точкой восстановления
            ->where('tickets_available', '<', 3)
            ->get();

        $restored = 0;
        $notified = 0;

        foreach ($users as $user) {
            // tickets_depleted_at хранит момент восстановления билета
            // Если текущее время >= момента восстановления → билет готов
            if (now() >= $user->tickets_depleted_at) {
                DB::beginTransaction();
                try {
                    $oldTickets = $user->tickets_available;
                    $user->tickets_available = min($user->tickets_available + 1, 3);
                    
                    // Если билетов все еще меньше 3, устанавливаем новую точку восстановления для следующего билета
                    if ($user->tickets_available < 3) {
                        $user->tickets_depleted_at = now()->addSeconds($restoreInterval);
                    } else {
                        // Билетов стало 3, сбрасываем точку
                        $user->tickets_depleted_at = null;
                    }
                    
                    $user->save();

                    // Отправляем уведомление о восстановлении билета
                    if ($oldTickets < 3 && $user->tickets_available > $oldTickets) {
                        TelegramNotificationService::notifyNewTicket($user);
                        $notified++;
                    }

                    DB::commit();
                    $restored++;
                    $this->line("✓ Восстановлен 1 билет пользователю {$user->telegram_id} ({$oldTickets} → {$user->tickets_available})");
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

