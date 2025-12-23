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
    protected $description = 'Отправка автоматических сообщений пользователям через заданный интервал после триггера';

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
        $trigger = $settings->broadcast_trigger ?? 'after_registration';

        if (empty($messageText)) {
            $this->warn('Текст сообщения не задан в настройках.');
            return Command::SUCCESS;
        }

        // Определяем поле для отсчета времени в зависимости от триггера
        $triggerField = $trigger === 'after_last_spin' ? 'last_spin_at' : 'created_at';

        // Находим всех пользователей с telegram_id
        // У которых прошло >= intervalHours с момента триггера
        // И которые еще не получали рассылку за этот период
        $query = User::whereNotNull('telegram_id');

        // Фильтруем по триггеру
        if ($trigger === 'after_last_spin') {
            // Для триггера "после последнего прокрута" - должен быть хотя бы один прокрут
            $query->whereNotNull('last_spin_at')
                ->whereRaw('TIMESTAMPDIFF(HOUR, last_spin_at, NOW()) >= ?', [$intervalHours]);
        } else {
            // Для триггера "после регистрации"
            $query->whereRaw('TIMESTAMPDIFF(HOUR, created_at, NOW()) >= ?', [$intervalHours]);
        }

        // Проверяем, что пользователь еще не получал рассылку за последний период
        // Если last_broadcast_sent_at null или прошло >= intervalHours с последней отправки
        $query->where(function ($q) use ($intervalHours) {
            $q->whereNull('last_broadcast_sent_at')
              ->orWhereRaw('TIMESTAMPDIFF(HOUR, last_broadcast_sent_at, NOW()) >= ?', [$intervalHours]);
        });

        $users = $query->get();

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($users as $user) {
            // Дополнительная проверка: убеждаемся, что прошло достаточно времени с момента триггера
            $triggerTime = $trigger === 'after_last_spin' ? $user->last_spin_at : $user->created_at;
            
            if (!$triggerTime) {
                $skipped++;
                continue;
            }

            $hoursSinceTrigger = now()->diffInHours($triggerTime);
            
            if ($hoursSinceTrigger < $intervalHours) {
                $skipped++;
                continue;
            }

            // Проверяем, не отправляли ли уже рассылку за этот период
            if ($user->last_broadcast_sent_at) {
                $hoursSinceLastBroadcast = now()->diffInHours($user->last_broadcast_sent_at);
                if ($hoursSinceLastBroadcast < $intervalHours) {
                    $skipped++;
                    continue;
                }
            }

            DB::beginTransaction();
            try {
                // Заменяем переменные в сообщении
                $personalizedMessage = $this->replaceVariables($messageText, $user);

                // Отправляем сообщение через Telegram Bot API напрямую
                $success = $this->sendTelegramMessage($user->telegram_id, $personalizedMessage);

                if ($success) {
                    // Обновляем last_broadcast_sent_at
                    $user->last_broadcast_sent_at = now();
                    $user->save();
                }

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

        $this->info("Готово! Отправлено: {$sent}, Ошибок: {$failed}, Пропущено: {$skipped}");

        return Command::SUCCESS;
    }

    /**
     * Отправить сообщение через Telegram Bot API с обработкой ошибок
     * 
     * @param int $telegramId
     * @param string $message
     * @return bool
     */
    private function sendTelegramMessage(int $telegramId, string $message): bool
    {
        $botToken = config('services.telegram.bot_token');
        
        if (!$botToken) {
            Log::warning('Telegram bot token not configured, cannot send broadcast message');
            return false;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::post(
                "https://api.telegram.org/bot{$botToken}/sendMessage",
                [
                    'chat_id' => $telegramId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                ]
            );

            if ($response->successful()) {
                return true;
            }

            $responseData = $response->json();
            $errorCode = $responseData['error_code'] ?? null;
            $errorDescription = $responseData['description'] ?? 'Unknown error';

            // Обработка специфичных ошибок
            if ($errorCode === 403) {
                // Бот заблокирован пользователем - исключаем из рассылок
                Log::info('Bot blocked by user, excluding from broadcasts', [
                    'telegram_id' => $telegramId,
                ]);
                // Можно пометить пользователя как заблокировавшего бота
                // Но пока просто пропускаем
            } elseif ($errorCode === 400) {
                // Некорректный telegram_id - логируем и исключаем
                Log::warning('Invalid telegram_id for broadcast', [
                    'telegram_id' => $telegramId,
                    'error' => $errorDescription,
                ]);
            } else {
                Log::error('Failed to send broadcast message via Telegram API', [
                    'telegram_id' => $telegramId,
                    'error_code' => $errorCode,
                    'error' => $errorDescription,
                ]);
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Exception sending broadcast message via Telegram API', [
                'telegram_id' => $telegramId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
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
