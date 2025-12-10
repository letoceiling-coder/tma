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
            $table->text('welcome_text')->nullable()->after('admin_username');
            $table->string('welcome_banner_url', 500)->nullable()->after('welcome_text');
            $table->json('welcome_buttons')->nullable()->after('welcome_banner_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wheel_settings', function (Blueprint $table) {
            $table->dropColumn(['welcome_text', 'welcome_banner_url', 'welcome_buttons']);
        });
    }
};
