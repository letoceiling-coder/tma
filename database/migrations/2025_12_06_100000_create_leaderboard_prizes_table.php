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
        Schema::create('leaderboard_prizes', function (Blueprint $table) {
            $table->id();
            $table->integer('rank')->unique(); // Место (1, 2, 3)
            $table->integer('prize_amount')->default(0); // Сумма приза в рублях
            $table->string('prize_description')->nullable(); // Описание приза
            $table->boolean('is_active')->default(true); // Активен ли приз
            $table->timestamps();
            
            $table->index('rank');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaderboard_prizes');
    }
};

