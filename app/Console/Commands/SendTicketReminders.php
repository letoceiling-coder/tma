<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TelegramNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendTicketReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wow:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Отправить персональные уведомления о доступности бесплатной прокрутки (24 часа после последней прокрутки)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Отправка персональных уведомлений о доступности бесплатной прокрутки...');

        // Получаем пользователей, которым нужно отправить уведомление:
        // 1. Есть telegram_id
        // 2. Есть билеты (tickets_available > 0)
        // 3. Был хотя бы один спин (last_spin_at не null)
        // 4. Прошло ровно 24 часа с момента последней прокрутки
        // 5. Уведомление еще не было отправлено после последней прокрутки
        //    (last_notification_sent_at null или меньше last_spin_at)
        $users = User::whereNotNull('telegram_id')
            ->where('tickets_available', '>', 0)
            ->whereNotNull('last_spin_at')
            ->where('last_spin_at', '<=', now()->subDay())
            ->where(function($query) {
                $query->whereNull('last_notification_sent_at')
                      ->orWhereColumn('last_notification_sent_at', '<', 'last_spin_at');
            })
            ->get();

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($users as $user) {
            try {
                // Дополнительная проверка: прошло ли минимум 24 часа
                $hoursSinceLastSpin = $user->last_spin_at->diffInHours(now());
                
                // Отправляем если прошло минимум 24 часа
                // Проверка в запросе уже гарантирует, что уведомление не было отправлено после последней прокрутки
                if ($hoursSinceLastSpin >= 24) {
                    if (TelegramNotificationService::notifyFreeSpinAvailable($user)) {
                        // Обновляем время отправки уведомления
                        $user->last_notification_sent_at = now();
                        $user->save();
                        
                        $sent++;
                        $this->line("✓ Уведомление отправлено пользователю {$user->telegram_id} (после {$hoursSinceLastSpin} часов)");
                    } else {
                        $failed++;
                        $this->warn("✗ Не удалось отправить пользователю {$user->telegram_id}");
                    }
                } else {
                    $skipped++;
                    $this->line("⊘ Пропущен пользователь {$user->telegram_id} (прошло {$hoursSinceLastSpin} часов, нужно минимум 24)");
                }
            } catch (\Exception $e) {
                $failed++;
                Log::error('Error sending free spin notification', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("✗ Ошибка для пользователя {$user->telegram_id}: {$e->getMessage()}");
            }
        }

        $this->info("Готово! Отправлено: {$sent}, Пропущено: {$skipped}, Ошибок: {$failed}");

        return Command::SUCCESS;
    }
}

