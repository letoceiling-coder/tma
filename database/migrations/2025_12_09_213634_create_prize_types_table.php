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
        Schema::create('prize_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Название приза (отображается в рулетке)
            $table->enum('type', ['money', 'ticket', 'gift', 'secret_box', 'empty', 'sponsor_gift'])->default('empty'); // Тип приза
            $table->integer('value')->default(0); // Значение (сумма денег, количество билетов и т.д.)
            $table->text('message')->nullable(); // Текст сообщения пользователю после выигрыша
            $table->enum('action', ['none', 'add_ticket'])->default('none'); // Действие (например, добавить билет)
            $table->string('icon_url')->nullable(); // URL иконки
            $table->boolean('is_active')->default(true); // Активен ли тип приза
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prize_types');
    }
};
