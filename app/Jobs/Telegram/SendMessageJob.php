<?php

namespace App\Jobs\Telegram;

use App\Telegram\Bot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int|string $chatId,
        protected string $text,
        protected array $params = []
    ) {
        $this->onQueue(config('telegram.notifications.queue', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(Bot $bot): void
    {
        try {
            $bot->sendMessage($this->chatId, $this->text, $this->params);
            
            Log::info('Telegram message sent', [
                'chat_id' => $this->chatId,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send Telegram message', [
                'chat_id' => $this->chatId,
                'error' => $e->getMessage(),
            ]);

            // Повторить попытку
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Telegram message job failed', [
            'chat_id' => $this->chatId,
            'error' => $exception->getMessage(),
        ]);
    }
}


