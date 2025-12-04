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
        Schema::create('spins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('spin_time');
            $table->enum('prize_type', ['money', 'ticket', 'secret_box', 'empty'])->default('empty');
            $table->integer('prize_value')->default(0);
            $table->foreignId('sector_id')->nullable()->constrained('wheel_sectors')->nullOnDelete();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('spin_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spins');
    }
};

