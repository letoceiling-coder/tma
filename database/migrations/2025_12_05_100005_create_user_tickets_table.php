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
        Schema::create('user_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('tickets_count')->default(1); // Количество билетов в этой записи
            $table->timestamp('restored_at')->nullable(); // Время восстановления билета
            $table->enum('source', ['free', 'star_exchange', 'admin_grant'])->default('free'); // Источник билета
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('restored_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_tickets');
    }
};

