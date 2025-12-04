<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LeaderboardPrize;

class LeaderboardPrizeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $prizes = [
            [
                'rank' => 1,
                'prize_amount' => 1500,
                'prize_description' => '1 место - Золото',
                'is_active' => true,
            ],
            [
                'rank' => 2,
                'prize_amount' => 1000,
                'prize_description' => '2 место - Серебро',
                'is_active' => true,
            ],
            [
                'rank' => 3,
                'prize_amount' => 500,
                'prize_description' => '3 место - Бронза',
                'is_active' => true,
            ],
        ];

        foreach ($prizes as $prize) {
            LeaderboardPrize::updateOrCreate(
                ['rank' => $prize['rank']],
                $prize
            );
        }
    }
}

