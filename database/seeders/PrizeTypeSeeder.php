<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PrizeType;
use Illuminate\Support\Facades\DB;

class PrizeTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $prizeTypes = [
            [
                'name' => 'Деньги 300 рублей',
                'type' => 'money',
                'value' => 300,
                'message' => 'Поздравляем! Вы выиграли 300 рублей!',
                'action' => 'none',
                'icon_url' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Деньги 500 рублей',
                'type' => 'money',
                'value' => 500,
                'message' => 'Поздравляем! Вы выиграли 500 рублей!',
                'action' => 'none',
                'icon_url' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Секретный бокс',
                'type' => 'secret_box',
                'value' => 0,
                'message' => 'Поздравляем! Вы выиграли секретный бокс!',
                'action' => 'none',
                'icon_url' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Подарок от спонсора',
                'type' => 'sponsor_gift',
                'value' => 0,
                'message' => 'Поздравляем! Вы выиграли подарок от спонсора!',
                'action' => 'none',
                'icon_url' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Билет +1 прокрут',
                'type' => 'ticket',
                'value' => 1,
                'message' => 'Вам начислен +1 билет',
                'action' => 'add_ticket',
                'icon_url' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Пусто',
                'type' => 'empty',
                'value' => 0,
                'message' => null,
                'action' => 'none',
                'icon_url' => null,
                'is_active' => true,
            ],
        ];

        foreach ($prizeTypes as $prizeType) {
            PrizeType::updateOrCreate(
                ['name' => $prizeType['name']],
                $prizeType
            );
        }
    }
}

