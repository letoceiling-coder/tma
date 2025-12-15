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
            if (!Schema::hasColumn('wheel_settings', 'send_ticket_notification')) {
                $table->boolean('send_ticket_notification')->default(true)->after('show_gift_button');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wheel_settings', function (Blueprint $table) {
            $table->dropColumn('send_ticket_notification');
        });
    }
};
