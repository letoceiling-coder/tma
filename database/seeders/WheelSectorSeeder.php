<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WheelSector;
use Illuminate\Support\Facades\DB;

class WheelSectorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Создаем или обновляем 12 секторов с начальными данными
        // Используем updateOrCreate чтобы seeder был идемпотентным
        // и не требовал truncate (который не работает с внешними ключами)
        // 
        // Соответствие с фронтендом (MainWheel.tsx wheelSegments):
        // sector 1: 0 -> empty
        // sector 2: 2000 -> money (2000)
        // sector 3: 0 -> empty
        // sector 4: 300 -> money (300)
        // sector 5: 500 -> money (500)
        // sector 6: 0 -> empty
        // sector 7: 0 -> empty
        // sector 8: 300 -> money (300)
        // sector 9: 300 -> money (300)
        // sector 10: 0 -> empty
        // sector 11: 1000 -> money (1000)
        // sector 12: 0 -> empty
        $sectors = [
            [
                'sector_number' => 1,
                'prize_type' => 'empty',
                'prize_value' => 0,
                'probability_percent' => 8.33, // 1/12 ≈ 8.33%
                'is_active' => true,
                'icon_url' => null, // Будет установлена через админку или команду импорта
            ],
            [
                'sector_number' => 2,
                'prize_type' => 'money',
                'prize_value' => 2000,
                'probability_percent' => 8.33,
                'is_active' => true,
                'icon_url' => null,
            ],
            [
                'sector_number' => 3,
                'prize_type' => 'empty',
                'prize_value' => 0,
                'probability_percent' => 8.33,
                'is_active' => true,
                'icon_url' => null,
            ],
            [
                'sector_number' => 4,
                'prize_type' => 'money',
                'prize_value' => 300,
                'probability_percent' => 8.33,
                'is_active' => true,
                'icon_url' => null,
            ],
            [
                'sector_number' => 5,
                'prize_type' => 'money',
                'prize_value' => 500,
                'probability_percent' => 8.33,
                'is_active' => true,
                'icon_url' => null,
            ],
            [
                'sector_number' => 6,
                'prize_type' => 'empty',
                'prize_value' => 0,
                'probability_percent' => 8.33,
                'is_active' => true,
                'icon_url' => null,
            ],
            [
                'sector_number' => 7,
                'prize_type' => 'empty',
                'prize_value' => 0,
                'probability_percent' => 8.33,
                'is_active' => true,
                'icon_url' => null,
            ],
            [
                'sector_number' => 8,
                'prize_type' => 'money',
                'prize_value' => 300,
                'probability_percent' => 8.33,
                'is_active' => true,
                'icon_url' => null,
            ],
            [
                'sector_number' => 9,
                'prize_type' => 'money',
                'prize_value' => 300,
                'probability_percent' => 8.33,
                'is_active' => true,
                'icon_url' => null,
            ],
            [
                'sector_number' => 10,
                'prize_type' => 'empty',
                'prize_value' => 0,
                'probability_percent' => 8.33,
                'is_active' => true,
                'icon_url' => null,
            ],
            [
                'sector_number' => 11,
                'prize_type' => 'money',
                'prize_value' => 1000,
                'probability_percent' => 8.33,
                'is_active' => true,
                'icon_url' => null,
            ],
            [
                'sector_number' => 12,
                'prize_type' => 'empty',
                'prize_value' => 0,
                'probability_percent' => 8.34, // Чуть больше чтобы сумма была 100%
                'is_active' => true,
                'icon_url' => null,
            ],
        ];

        foreach ($sectors as $sector) {
            WheelSector::updateOrCreate(
                ['sector_number' => $sector['sector_number']],
                $sector
            );
        }

        $this->command->info('Создано/обновлено 12 секторов рулетки');
    }
}

