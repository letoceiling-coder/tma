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
        Schema::create('leaderboard_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('month'); // Месяц (1-12)
            $table->integer('year'); // Год
            $table->integer('invites_count')->default(0); // Количество приглашений за месяц
            $table->integer('rank')->nullable(); // Позиция в рейтинге
            $table->integer('prize_amount')->default(0); // Размер приза
            $table->boolean('prize_paid')->default(false); // Выплачен ли приз
            $table->timestamps();
            
            $table->unique(['user_id', 'month', 'year']);
            $table->index(['month', 'year']);
            $table->index('rank');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaderboard_snapshots');
    }
};

