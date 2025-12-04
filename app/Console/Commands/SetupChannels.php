<?php

namespace App\Console\Commands;

use App\Models\Channel;
use Illuminate\Console\Command;

class SetupChannels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wow:setup-channels';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Настроить каналы для обязательной подписки (удаляет все существующие и добавляет новые)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Настройка каналов для обязательной подписки...');

        // Удаляем все существующие каналы
        $deleted = Channel::query()->delete();
        $this->info("Удалено существующих каналов: {$deleted}");

        // Добавляем каналы
        $channels = [
            [
                'username' => 'neeklo_studio',
                'title' => 'Neeklo Studio',
                'is_active' => true,
                'priority' => 1,
            ],
            [
                'username' => 'neiroitishka',
                'title' => 'Neiroitishka',
                'is_active' => true,
                'priority' => 2,
            ],
        ];

        foreach ($channels as $channelData) {
            Channel::create($channelData);
            $this->info("✅ Канал @{$channelData['username']} успешно добавлен!");
        }

        // Показываем список активных каналов
        $channels = Channel::where('is_active', true)->get();
        
        $this->info("\nТекущие активные каналы:");
        $this->table(
            ['ID', 'Username', 'Название', 'Приоритет', 'Активен'],
            $channels->map(function ($channel) {
                return [
                    $channel->id,
                    '@' . $channel->username,
                    $channel->title,
                    $channel->priority,
                    $channel->is_active ? 'Да' : 'Нет',
                ];
            })->toArray()
        );

        return Command::SUCCESS;
    }
}

