<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Добавляем external_id в support_tickets
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->uuid('external_id')->nullable()->after('id');
            $table->index('external_id');
        });

        // Переименовываем theme в subject (MySQL требует через DB::statement)
        if (Schema::hasColumn('support_tickets', 'theme')) {
            DB::statement('ALTER TABLE support_tickets CHANGE theme subject VARCHAR(255) NOT NULL');
        }

        // Добавляем external_message_id в support_messages
        Schema::table('support_messages', function (Blueprint $table) {
            $table->string('external_message_id')->nullable()->after('id');
            $table->index('external_message_id');
        });

        // Изменяем enum sender: local|crm → tma|crm
        // MySQL не поддерживает изменение ENUM напрямую, делаем через ALTER
        DB::statement("ALTER TABLE support_messages MODIFY COLUMN sender ENUM('tma', 'crm') NOT NULL");
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropIndex(['external_message_id']);
            $table->dropColumn('external_message_id');
            DB::statement("ALTER TABLE support_messages MODIFY COLUMN sender ENUM('local', 'crm') NOT NULL");
        });

        // Переименовываем обратно subject в theme
        if (Schema::hasColumn('support_tickets', 'subject')) {
            DB::statement('ALTER TABLE support_tickets CHANGE subject theme VARCHAR(255) NOT NULL');
        }
        
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex(['external_id']);
            $table->dropColumn('external_id');
        });
    }
};

