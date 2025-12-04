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
    protected $description = 'Отправить напоминания пользователям о доступных билетах';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Отправка напоминаний о билетах...');

        // Получаем пользователей с билетами, которые не крутили более 24 часов
        $users = User::whereNotNull('telegram_id')
            ->where('tickets_available', '>', 0)
            ->where(function($query) {
                $query->whereNull('last_spin_at')
                      ->orWhere('last_spin_at', '<', now()->subDay());
            })
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($users as $user) {
            try {
                if (TelegramNotificationService::notifyReminder($user)) {
                    $sent++;
                    $this->line("✓ Напоминание отправлено пользователю {$user->telegram_id}");
                } else {
                    $failed++;
                    $this->warn("✗ Не удалось отправить пользователю {$user->telegram_id}");
                }
            } catch (\Exception $e) {
                $failed++;
                Log::error('Error sending reminder', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("✗ Ошибка для пользователя {$user->telegram_id}: {$e->getMessage()}");
            }
        }

        $this->info("Готово! Отправлено: {$sent}, Ошибок: {$failed}");

        return Command::SUCCESS;
    }
}

