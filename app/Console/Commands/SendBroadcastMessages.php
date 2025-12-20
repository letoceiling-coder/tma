<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WheelSetting;
use App\Models\BroadcastLog;
use App\Services\TelegramNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendBroadcastMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wow:send-broadcast-messages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Отправка автоматических сообщений пользователям через заданный интервал после регистрации';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Проверка автоматической рассылки сообщений...');

        $settings = WheelSetting::getSettings();

        // Проверяем, включена ли рассылка
        if (!$settings->broadcast_enabled) {
            $this->info('Автоматическая рассылка отключена в настройках.');
            return Command::SUCCESS;
        }

        $intervalHours = $settings->broadcast_interval_hours ?? 24;
        $messageText = $settings->broadcast_message_text ?? 'Привет! У тебя есть новые возможности. Проверь приложение!';

        if (empty($messageText)) {
            $this->warn('Текст сообщения не задан в настройках.');
            return Command::SUCCESS;
        }

        // Находим всех пользователей с telegram_id
        // У которых прошло >= intervalHours с момента регистрации
        // И которым еще не отправляли сообщение (нет записи в broadcast_logs со статусом success)
        $users = User::whereNotNull('telegram_id')
            ->whereRaw('TIMESTAMPDIFF(HOUR, created_at, NOW()) >= ?', [$intervalHours])
            ->whereDoesntHave('broadcastLogs', function ($query) {
                $query->where('status', 'success');
            })
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($users as $user) {
            DB::beginTransaction();
            try {
                // Заменяем переменные в сообщении
                $personalizedMessage = $this->replaceVariables($messageText, $user);

                // Отправляем сообщение
                $success = TelegramNotificationService::sendNotification(
                    $user->telegram_id,
                    $personalizedMessage
                );

                // Записываем в лог
                BroadcastLog::create([
                    'user_id' => $user->id,
                    'message_text' => $personalizedMessage,
                    'sent_at' => now(),
                    'status' => $success ? 'success' : 'failed',
                    'error_message' => $success ? null : 'Failed to send message via Telegram API',
                ]);

                DB::commit();

                if ($success) {
                    $sent++;
                    $this->line("✓ Сообщение отправлено пользователю {$user->telegram_id}");
                } else {
                    $failed++;
                    $this->warn("✗ Не удалось отправить сообщение пользователю {$user->telegram_id}");
                }
            } catch (\Exception $e) {
                DB::rollBack();
                $failed++;
                $this->error("✗ Ошибка для пользователя {$user->telegram_id}: {$e->getMessage()}");

                // Записываем ошибку в лог
                try {
                    BroadcastLog::create([
                        'user_id' => $user->id,
                        'message_text' => $messageText,
                        'sent_at' => now(),
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);
                } catch (\Exception $logException) {
                    Log::error('Failed to create broadcast log', [
                        'user_id' => $user->id,
                        'error' => $logException->getMessage(),
                    ]);
                }

                Log::error('Error sending broadcast message', [
                    'user_id' => $user->id,
                    'telegram_id' => $user->telegram_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("Готово! Отправлено: {$sent}, Ошибок: {$failed}");

        return Command::SUCCESS;
    }

    /**
     * Заменить переменные в сообщении
     * 
     * @param string $message
     * @param User $user
     * @return string
     */
    private function replaceVariables(string $message, User $user): string
    {
        $replacements = [
            '{{username}}' => $user->name ?? 'друг',
            '{{tickets_count}}' => (string)($user->tickets_available ?? 0),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }
}
