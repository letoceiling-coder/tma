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
            if (!Schema::hasColumn('wheel_settings', 'daily_tickets')) {
                $table->integer('daily_tickets')->default(1)->after('initial_tickets_count');
            }
            if (!Schema::hasColumn('wheel_settings', 'default_daily_tickets')) {
                $table->integer('default_daily_tickets')->default(1)->after('daily_tickets');
            }
            if (!Schema::hasColumn('wheel_settings', 'stars_enabled')) {
                $table->boolean('stars_enabled')->default(true)->after('stars_per_ticket_purchase');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wheel_settings', function (Blueprint $table) {
            $table->dropColumn(['daily_tickets', 'default_daily_tickets', 'stars_enabled']);
        });
    }
};
