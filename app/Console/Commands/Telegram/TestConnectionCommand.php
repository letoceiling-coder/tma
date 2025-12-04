<?php

namespace App\Console\Commands\Telegram;

use App\Telegram\Bot;
use Illuminate\Console\Command;

class TestConnectionCommand extends Command
{
    protected $signature = 'telegram:test';

    protected $description = 'Проверить подключение к Telegram Bot API';

    public function handle(): int
    {
        $this->info('Testing Telegram Bot API connection...');
        $this->newLine();

        $bot = app(Bot::class);

        try {
            // Тест 1: getMe
            $this->info('1. Testing getMe()...');
            $me = $bot->getMe();

            $this->info('✓ Connection successful!');
            $this->newLine();

            $this->table(
                ['Parameter', 'Value'],
                [
                    ['Bot ID', $me['id']],
                    ['Bot Name', $me['first_name']],
                    ['Username', '@' . ($me['username'] ?? 'N/A')],
                    ['Can Join Groups', $me['can_join_groups'] ? 'Yes' : 'No'],
                    ['Can Read Messages', $me['can_read_all_group_messages'] ? 'Yes' : 'No'],
                    ['Supports Inline', $me['supports_inline_queries'] ? 'Yes' : 'No'],
                ]
            );

            // Тест 2: Webhook info
            $this->newLine();
            $this->info('2. Testing webhook info...');
            $webhookInfo = $bot->getWebhookInfo();
            
            $webhookStatus = isset($webhookInfo['url']) && !empty($webhookInfo['url']) 
                ? '✓ Active' 
                : '✗ Not set';
            
            $this->line("Webhook status: {$webhookStatus}");
            
            if (!empty($webhookInfo['url'])) {
                $this->line("URL: {$webhookInfo['url']}");
                $this->line("Pending updates: " . ($webhookInfo['pending_update_count'] ?? 0));
            }

            $this->newLine();
            $this->info('🎉 All tests passed!');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ Connection failed!');
            $this->error('Error: ' . $e->getMessage());
            $this->newLine();
            $this->warn('Please check:');
            $this->line('  1. TELEGRAM_BOT_TOKEN is set in .env');
            $this->line('  2. Token is valid');
            $this->line('  3. Internet connection is working');

            return self::FAILURE;
        }
    }
}

