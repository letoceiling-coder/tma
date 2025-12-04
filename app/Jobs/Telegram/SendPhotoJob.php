<?php

namespace App\Jobs\Telegram;

use App\Telegram\Bot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPhotoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int|string $chatId,
        protected string $photo,
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
            $bot->sendPhoto($this->chatId, $this->photo, $this->params);
            
            Log::info('Telegram photo sent', [
                'chat_id' => $this->chatId,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send Telegram photo', [
                'chat_id' => $this->chatId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Telegram photo job failed', [
            'chat_id' => $this->chatId,
            'error' => $exception->getMessage(),
        ]);
    }
}

