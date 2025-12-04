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
        Schema::table('users', function (Blueprint $table) {
            // Добавляем колонку без указания позиции, так как она может выполняться
            // до миграции, которая добавляет tickets_available и last_spin_at
            if (!Schema::hasColumn('users', 'tickets_depleted_at')) {
                $table->timestamp('tickets_depleted_at')->nullable()
                    ->comment('Время когда билеты закончились (стали 0) - точка начала отсчета для восстановления');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('tickets_depleted_at');
        });
    }
};

