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
        Schema::create('payments_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('error_type', 100); // Тип ошибки
            $table->text('error_message'); // Сообщение об ошибке
            $table->json('request_payload')->nullable(); // Данные запроса
            $table->integer('response_code')->nullable(); // HTTP код ответа
            $table->json('response_data')->nullable(); // Данные ответа
            $table->string('payment_id')->nullable(); // ID платежа (если был)
            $table->timestamp('timestamp')->useCurrent(); // Время ошибки
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('error_type');
            $table->index('timestamp');
            $table->index('payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments_errors');
    }
};
