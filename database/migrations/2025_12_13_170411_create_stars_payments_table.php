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
        Schema::create('stars_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('amount')->default(50); // Количество звезд
            $table->string('purpose', 100)->default('buy_20_spins'); // Назначение платежа
            $table->enum('status', ['pending', 'success', 'failed', 'cancelled'])->default('pending');
            $table->string('payment_id')->nullable()->unique(); // ID платежа от Telegram
            $table->string('invoice_url')->nullable(); // URL инвойса
            $table->json('payload')->nullable(); // Дополнительные данные
            $table->json('telegram_response')->nullable(); // Ответ от Telegram
            $table->timestamp('paid_at')->nullable(); // Время оплаты
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('status');
            $table->index('payment_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stars_payments');
    }
};
