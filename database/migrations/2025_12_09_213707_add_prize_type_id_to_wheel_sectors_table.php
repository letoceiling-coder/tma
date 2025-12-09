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
        Schema::table('wheel_sectors', function (Blueprint $table) {
            $table->foreignId('prize_type_id')->nullable()->after('prize_type')->constrained('prize_types')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wheel_sectors', function (Blueprint $table) {
            $table->dropForeign(['prize_type_id']);
            $table->dropColumn('prize_type_id');
        });
    }
};
