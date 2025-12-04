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
        // Для MySQL нужно использовать прямой SQL запрос для изменения ENUM
        DB::statement("ALTER TABLE `user_tickets` MODIFY COLUMN `source` ENUM('free', 'star_exchange', 'admin_grant') DEFAULT 'free'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Возвращаем обратно к старому варианту без admin_grant
        DB::statement("ALTER TABLE `user_tickets` MODIFY COLUMN `source` ENUM('free', 'star_exchange') DEFAULT 'free'");
    }
};

