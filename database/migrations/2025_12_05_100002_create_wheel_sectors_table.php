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
        Schema::create('wheel_sectors', function (Blueprint $table) {
            $table->id();
            $table->integer('sector_number')->unique(); // 1-12
            $table->enum('prize_type', ['money', 'ticket', 'secret_box', 'empty'])->default('empty');
            $table->integer('prize_value')->default(0); // Для денежных призов (300, 500, 1000 и т.д.)
            $table->string('icon_url')->nullable();
            $table->decimal('probability_percent', 5, 2)->default(0); // Вероятность выпадения (0-100)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wheel_sectors');
    }
};

