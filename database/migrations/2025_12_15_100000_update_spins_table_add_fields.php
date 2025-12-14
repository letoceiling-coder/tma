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
        Schema::table('spins', function (Blueprint $table) {
            // Добавляем поле prize_name для хранения читаемого названия приза
            if (!Schema::hasColumn('spins', 'prize_name')) {
                $table->string('prize_name')->nullable()->after('prize_value');
            }
            
            // Добавляем поля для внешних подарков от спонсоров
            if (!Schema::hasColumn('spins', 'external_gift_id')) {
                $table->string('external_gift_id')->nullable()->after('prize_name');
            }
            if (!Schema::hasColumn('spins', 'sponsor_name')) {
                $table->string('sponsor_name')->nullable()->after('external_gift_id');
            }
            if (!Schema::hasColumn('spins', 'delivery_status')) {
                $table->enum('delivery_status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])->nullable()->after('sponsor_name');
            }
        });

        // ENUM уже обновлен в миграции 2025_12_13_155341, но на всякий случай проверяем
        // DB::statement("ALTER TABLE spins MODIFY COLUMN prize_type ENUM('money', 'ticket', 'secret_box', 'empty', 'gift', 'sponsor_gift') DEFAULT 'empty'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spins', function (Blueprint $table) {
            $table->dropColumn([
                'sector_number',
                'prize_name',
                'external_gift_id',
                'sponsor_name',
                'delivery_status',
            ]);
        });

        // Возвращаем ENUM к исходному состоянию
        DB::statement("ALTER TABLE spins MODIFY COLUMN prize_type ENUM('money', 'ticket', 'secret_box', 'empty') DEFAULT 'empty'");
    }
};
