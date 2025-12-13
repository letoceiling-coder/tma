<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Удаляет все таблицы, связанные с поддержкой
     */
    public function up(): void
    {
        // Удаляем таблицы в правильном порядке (сначала зависимые)
        if (Schema::hasTable('message_sync_logs')) {
            Schema::dropIfExists('message_sync_logs');
        }

        if (Schema::hasTable('support_ticket_messages')) {
            Schema::dropIfExists('support_ticket_messages');
        }

        if (Schema::hasTable('ticket_chats')) {
            Schema::dropIfExists('ticket_chats');
        }

        // Удаляем колонку chat_id из support_tickets, если она существует
        if (Schema::hasTable('support_tickets')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                if (Schema::hasColumn('support_tickets', 'chat_id')) {
                    $table->dropForeign(['chat_id']);
                    $table->dropColumn('chat_id');
                }
            });
        }

        // Удаляем таблицу support_tickets
        if (Schema::hasTable('support_tickets')) {
            Schema::dropIfExists('support_tickets');
        }
    }

    /**
     * Reverse the migrations.
     * ВНИМАНИЕ: Эта миграция не восстанавливает таблицы поддержки
     * Если нужен откат, используйте git для восстановления миграций
     */
    public function down(): void
    {
        // Не восстанавливаем таблицы, так как функциональность поддержки удалена
        // Для восстановления используйте git checkout соответствующих миграций
    }
};
