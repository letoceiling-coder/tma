<?php

namespace App\Jobs\Telegram;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SendBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $message,
        protected array $params = [],
        protected ?array $userIds = null
    ) {
        $this->onQueue(config('telegram.notifications.queue', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Получаем пользователей
            $query = User::whereNotNull('telegram_id');
            
            if ($this->userIds !== null) {
                $query->whereIn('id', $this->userIds);
            }

            $users = $query->get();
            
            $sent = 0;
            $failed = 0;

            foreach ($users as $user) {
                try {
                    // Отправляем каждое сообщение через отдельный job с задержкой
                    SendMessageJob::dispatch($user->telegram_id, $this->message, $this->params)
                        ->delay(now()->addSeconds($sent * 0.1)); // 100ms между сообщениями
                    
                    $sent++;
                    
                } catch (\Exception $e) {
                    $failed++;
                    Log::warning('Failed to queue message for user', [
                        'user_id' => $user->id,
                        'telegram_id' => $user->telegram_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Broadcast completed', [
                'total' => $users->count(),
                'sent' => $sent,
                'failed' => $failed,
            ]);

        } catch (\Exception $e) {
            Log::error('Broadcast job failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

