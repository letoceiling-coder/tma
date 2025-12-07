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
        Schema::table('spins', function (Blueprint $table) {
            $table->integer('sector_number')->nullable()->after('sector_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spins', function (Blueprint $table) {
            $table->dropColumn('sector_number');
        });
    }
};

