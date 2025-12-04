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
            $table->bigInteger('telegram_id')->nullable()->unique()->after('id');
            $table->string('username')->nullable()->after('telegram_id');
            $table->string('avatar_url')->nullable()->after('username');
            $table->integer('stars_balance')->default(0)->after('avatar_url');
            $table->integer('tickets_available')->default(0)->after('stars_balance');
            $table->timestamp('last_spin_at')->nullable()->after('tickets_available');
            $table->foreignId('invited_by')->nullable()->after('last_spin_at')->constrained('users')->nullOnDelete();
            $table->integer('total_spins')->default(0)->after('invited_by');
            $table->integer('total_wins')->default(0)->after('total_spins');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['invited_by']);
            $table->dropColumn([
                'telegram_id',
                'username',
                'avatar_url',
                'stars_balance',
                'tickets_available',
                'last_spin_at',
                'invited_by',
                'total_spins',
                'total_wins',
            ]);
        });
    }
};

