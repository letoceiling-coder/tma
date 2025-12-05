<?php

namespace App\Console\Commands\Telegram;

use App\Telegram\Bot;
use Illuminate\Console\Command;

class SetWebhookCommand extends Command
{
    protected $signature = 'telegram:set-webhook
                          {url? : Webhook URL (if not provided, uses config)}
                          {--delete : Delete webhook before setting}';

    protected $description = 'Установить webhook для Telegram бота';

    public function handle(): int
    {
        $bot = app(Bot::class);
        
        $url = $this->argument('url') ?? config('telegram.webhook_url');

        if (!$url) {
            $this->error('Webhook URL not provided. Set TELEGRAM_WEBHOOK_URL in .env');
            return self::FAILURE;
        }

        try {
            // Удалить старый webhook если нужно
            if ($this->option('delete')) {
                $this->info('Deleting old webhook...');
                $bot->deleteWebhook(true);
            }

            $this->info("Setting webhook to: {$url}");

            $params = [];
            
            // Secret token
            if ($secretToken = config('telegram.webhook.secret_token')) {
                $params['secret_token'] = $secretToken;
            }

            // Allowed updates
            if ($allowedUpdates = config('telegram.webhook.allowed_updates')) {
                $params['allowed_updates'] = $allowedUpdates;
            }

            // Max connections
            if ($maxConnections = config('telegram.webhook.max_connections')) {
                $params['max_connections'] = $maxConnections;
            }

            $result = $bot->setWebhook($url, $params);

            $this->info('✓ Webhook set successfully!');
            $this->table(
                ['Parameter', 'Value'],
                [
                    ['URL', $url],
                    ['Secret Token', $secretToken ? 'Set' : 'Not set'],
                    ['Max Connections', $maxConnections ?? 40],
                ]
            );

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to set webhook: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}


