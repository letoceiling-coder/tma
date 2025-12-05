<?php

namespace App\Console\Commands\Telegram;

use App\Telegram\Bot;
use Illuminate\Console\Command;

class GetWebhookInfoCommand extends Command
{
    protected $signature = 'telegram:webhook-info';

    protected $description = 'Получить информацию о webhook';

    public function handle(): int
    {
        $bot = app(Bot::class);

        try {
            $info = $bot->getWebhookInfo();

            $this->info('Webhook Information:');
            $this->newLine();

            $data = [
                ['URL', $info['url'] ?? 'Not set'],
                ['Has Custom Certificate', $info['has_custom_certificate'] ? 'Yes' : 'No'],
                ['Pending Update Count', $info['pending_update_count'] ?? 0],
                ['Max Connections', $info['max_connections'] ?? 40],
            ];

            if (isset($info['ip_address'])) {
                $data[] = ['IP Address', $info['ip_address']];
            }

            if (isset($info['last_error_date'])) {
                $data[] = ['Last Error Date', date('Y-m-d H:i:s', $info['last_error_date'])];
                $data[] = ['Last Error Message', $info['last_error_message'] ?? 'N/A'];
            }

            if (isset($info['last_synchronization_error_date'])) {
                $data[] = ['Last Sync Error', date('Y-m-d H:i:s', $info['last_synchronization_error_date'])];
            }

            $this->table(['Parameter', 'Value'], $data);

            if (isset($info['allowed_updates']) && !empty($info['allowed_updates'])) {
                $this->newLine();
                $this->info('Allowed Updates:');
                foreach ($info['allowed_updates'] as $update) {
                    $this->line('  - ' . $update);
                }
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to get webhook info: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}


