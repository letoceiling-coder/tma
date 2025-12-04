<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Channel;

class ChannelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Каналы для обязательной подписки
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
            Channel::updateOrCreate(
                ['username' => $channelData['username']],
                $channelData
            );
        }

        $this->command->info('✅ Каналы для подписки созданы/обновлены: ' . count($channels));
    }
}

