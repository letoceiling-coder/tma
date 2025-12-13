<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // В MySQL нельзя просто добавить значения в ENUM, нужно изменить весь тип колонки
        DB::statement("ALTER TABLE spins MODIFY COLUMN prize_type ENUM('money', 'ticket', 'secret_box', 'empty', 'gift', 'sponsor_gift') DEFAULT 'empty'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Возвращаем к исходному состоянию (без gift и sponsor_gift)
        DB::statement("ALTER TABLE spins MODIFY COLUMN prize_type ENUM('money', 'ticket', 'secret_box', 'empty') DEFAULT 'empty'");
    }
};
