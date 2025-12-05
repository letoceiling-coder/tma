<?php

namespace App\Console\Commands\Telegram;

use App\Telegram\Bot;
use Illuminate\Console\Command;

class DeleteWebhookCommand extends Command
{
    protected $signature = 'telegram:delete-webhook
                          {--drop-pending : Drop all pending updates}';

    protected $description = 'Удалить webhook';

    public function handle(): int
    {
        $bot = app(Bot::class);

        try {
            $dropPending = $this->option('drop-pending');

            if ($dropPending) {
                if (!$this->confirm('Are you sure you want to drop all pending updates?')) {
                    return self::SUCCESS;
                }
            }

            $this->info('Deleting webhook...');
            $bot->deleteWebhook($dropPending);

            $this->info('✓ Webhook deleted successfully!');
            
            if ($dropPending) {
                $this->warn('All pending updates have been dropped.');
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to delete webhook: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}


