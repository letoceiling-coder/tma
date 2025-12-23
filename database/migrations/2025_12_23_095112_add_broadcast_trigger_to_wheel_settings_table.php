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
            if (!Schema::hasColumn('wheel_settings', 'broadcast_trigger')) {
                $table->string('broadcast_trigger', 50)->default('after_registration')->after('broadcast_interval_hours');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wheel_settings', function (Blueprint $table) {
            if (Schema::hasColumn('wheel_settings', 'broadcast_trigger')) {
                $table->dropColumn('broadcast_trigger');
            }
        });
    }
};
