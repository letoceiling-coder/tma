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
            if (!Schema::hasColumn('wheel_settings', 'broadcast_enabled')) {
                $table->boolean('broadcast_enabled')->default(true)->after('ticket_accrual_notifications_enabled');
            }
            if (!Schema::hasColumn('wheel_settings', 'broadcast_message_text')) {
                $table->text('broadcast_message_text')->nullable()->after('broadcast_enabled');
            }
            if (!Schema::hasColumn('wheel_settings', 'broadcast_interval_hours')) {
                $table->integer('broadcast_interval_hours')->default(24)->after('broadcast_message_text');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wheel_settings', function (Blueprint $table) {
            if (Schema::hasColumn('wheel_settings', 'broadcast_interval_hours')) {
                $table->dropColumn('broadcast_interval_hours');
            }
            if (Schema::hasColumn('wheel_settings', 'broadcast_message_text')) {
                $table->dropColumn('broadcast_message_text');
            }
            if (Schema::hasColumn('wheel_settings', 'broadcast_enabled')) {
                $table->dropColumn('broadcast_enabled');
            }
        });
    }
};
