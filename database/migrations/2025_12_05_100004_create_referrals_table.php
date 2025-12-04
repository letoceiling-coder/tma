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
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inviter_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('invited_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('invited_at');
            $table->timestamps();
            
            // Уникальная пара - один пользователь не может быть приглашен одним человеком дважды
            $table->unique(['inviter_id', 'invited_id']);
            $table->index('inviter_id');
            $table->index('invited_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};

