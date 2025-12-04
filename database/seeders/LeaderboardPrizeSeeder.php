<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LeaderboardPrize;

class LeaderboardPrizeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    /**
     * Run the database seeds.
     * 
     * Создает 10 мест с призами:
     * - 1 место: 1500₽
     * - 2 место: 100₽
     * - 3 место: 500₽
     * - 4-10 места: 300₽
     */
    public function run(): void
    {
        $prizes = [
            [
                'rank' => 1,
                'prize_amount' => 1500,
                'prize_description' => '1 место',
                'is_active' => true,
            ],
            [
                'rank' => 2,
                'prize_amount' => 100,
                'prize_description' => '2 место',
                'is_active' => true,
            ],
            [
                'rank' => 3,
                'prize_amount' => 500,
                'prize_description' => '3 место',
                'is_active' => true,
            ],
        ];

        // Места 4-10 с призом 300₽
        for ($rank = 4; $rank <= 10; $rank++) {
            $prizes[] = [
                'rank' => $rank,
                'prize_amount' => 300,
                'prize_description' => "{$rank} место",
                'is_active' => true,
            ];
        }

        // Перезаписываем все призы (удаляем старые и создаем новые)
        LeaderboardPrize::truncate();

        foreach ($prizes as $prize) {
            LeaderboardPrize::create($prize);
        }
    }
}

