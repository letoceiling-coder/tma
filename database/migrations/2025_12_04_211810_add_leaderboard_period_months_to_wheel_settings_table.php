<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('wheel_settings', function (Blueprint $table) {
            // Добавляем после always_empty_mode, так как ticket_restore_hours добавляется позже
            $table->integer('leaderboard_period_months')->default(1)->after('always_empty_mode')
                ->comment('Период отображения лидерборда в месяцах (1, 2, 3, 4, 5, 6, 12)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wheel_settings', function (Blueprint $table) {
            $table->dropColumn('leaderboard_period_months');
        });
    }
};
