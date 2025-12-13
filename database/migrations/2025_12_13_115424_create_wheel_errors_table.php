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
        Schema::create('wheel_errors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('error_type', 100)->index(); // Тип ошибки: probability_error, sector_selection_error, prize_award_error, etc.
            $table->text('error_message'); // Сообщение об ошибке
            $table->json('sector_config_snapshot')->nullable(); // Снимок конфигурации секторов на момент ошибки
            $table->json('request_payload')->nullable(); // Данные запроса, вызвавшего ошибку
            $table->timestamp('timestamp')->useCurrent()->index(); // Время ошибки
            $table->timestamps();
            
            // Внешний ключ на users (опционально, так как user_id может быть null)
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wheel_errors');
    }
};
