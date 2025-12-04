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
        $sectors = [
            [
                'sector_number' => 1,
                'prize_type' => 'empty',
                'prize_value' => 0,
                'probability_percent' => 10.00,
                'is_active' => true,
            ],
            [
                'sector_number' => 2,
                'prize_type' => 'money',
                'prize_value' => 300,
                'probability_percent' => 8.00,
                'is_active' => true,
            ],
            [
                'sector_number' => 3,
                'prize_type' => 'empty',
                'prize_value' => 0,
                'probability_percent' => 12.00,
                'is_active' => true,
            ],
            [
                'sector_number' => 4,
                'prize_type' => 'money',
                'prize_value' => 500,
                'probability_percent' => 6.00,
                'is_active' => true,
            ],
            [
                'sector_number' => 5,
                'prize_type' => 'empty',
                'prize_value' => 0,
                'probability_percent' => 15.00,
                'is_active' => true,
            ],
            [
                'sector_number' => 6,
                'prize_type' => 'ticket',
                'prize_value' => 1,
                'probability_percent' => 5.00,
                'is_active' => true,
            ],
            [
                'sector_number' => 7,
                'prize_type' => 'empty',
                'prize_value' => 0,
                'probability_percent' => 10.00,
                'is_active' => true,
            ],
            [
                'sector_number' => 8,
                'prize_type' => 'money',
                'prize_value' => 300,
                'probability_percent' => 8.00,
                'is_active' => true,
            ],
            [
                'sector_number' => 9,
                'prize_type' => 'money',
                'prize_value' => 300,
                'probability_percent' => 8.00,
                'is_active' => true,
            ],
            [
                'sector_number' => 10,
                'prize_type' => 'empty',
                'prize_value' => 0,
                'probability_percent' => 12.00,
                'is_active' => true,
            ],
            [
                'sector_number' => 11,
                'prize_type' => 'money',
                'prize_value' => 1000,
                'probability_percent' => 4.00,
                'is_active' => true,
            ],
            [
                'sector_number' => 12,
                'prize_type' => 'empty',
                'prize_value' => 0,
                'probability_percent' => 2.00,
                'is_active' => true,
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

