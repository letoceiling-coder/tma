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
            if (!Schema::hasColumn('wheel_settings', 'ticket_accrual_enabled')) {
                $table->boolean('ticket_accrual_enabled')->default(true)->after('send_ticket_notification');
            }
            if (!Schema::hasColumn('wheel_settings', 'ticket_accrual_interval_hours')) {
                $table->integer('ticket_accrual_interval_hours')->default(24)->after('ticket_accrual_enabled');
            }
            if (!Schema::hasColumn('wheel_settings', 'ticket_accrual_notifications_enabled')) {
                $table->boolean('ticket_accrual_notifications_enabled')->default(true)->after('ticket_accrual_interval_hours');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wheel_settings', function (Blueprint $table) {
            if (Schema::hasColumn('wheel_settings', 'ticket_accrual_notifications_enabled')) {
                $table->dropColumn('ticket_accrual_notifications_enabled');
            }
            if (Schema::hasColumn('wheel_settings', 'ticket_accrual_interval_hours')) {
                $table->dropColumn('ticket_accrual_interval_hours');
            }
            if (Schema::hasColumn('wheel_settings', 'ticket_accrual_enabled')) {
                $table->dropColumn('ticket_accrual_enabled');
            }
        });
    }
};
