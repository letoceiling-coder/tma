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
        Schema::table('wheel_errors', function (Blueprint $table) {
            $table->unsignedBigInteger('sector_id')->nullable()->after('user_id');
            $table->string('prize_type', 50)->nullable()->after('sector_id');
            $table->decimal('random_value', 8, 2)->nullable()->after('prize_type');
            $table->json('expected_sector_result')->nullable()->after('random_value');
            
            $table->index('sector_id');
            $table->index('prize_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wheel_errors', function (Blueprint $table) {
            $table->dropIndex(['prize_type']);
            $table->dropIndex(['sector_id']);
            $table->dropColumn(['sector_id', 'prize_type', 'random_value', 'expected_sector_result']);
        });
    }
};
