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
            if (!Schema::hasColumn('users', 'last_ticket_accrual_at')) {
                $table->timestamp('last_ticket_accrual_at')->nullable()->after('last_ticket_received_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'last_ticket_accrual_at')) {
                $table->dropColumn('last_ticket_accrual_at');
            }
        });
    }
};
