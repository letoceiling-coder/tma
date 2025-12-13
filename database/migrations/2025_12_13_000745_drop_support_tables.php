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
        // Шаг 1: Удаляем foreign key из support_tickets перед удалением таблиц
        if (Schema::hasTable('support_tickets')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                // Проверяем и удаляем все возможные foreign keys
                if (Schema::hasColumn('support_tickets', 'chat_id')) {
                    try {
                        $table->dropForeign(['chat_id']);
                    } catch (\Exception $e) {
                        // Игнорируем ошибку, если foreign key уже удален
                    }
                }
            });
        }

        // Шаг 2: Удаляем таблицы в правильном порядке (сначала зависимые)
        if (Schema::hasTable('message_sync_logs')) {
            Schema::dropIfExists('message_sync_logs');
        }

        if (Schema::hasTable('support_ticket_messages')) {
            Schema::dropIfExists('support_ticket_messages');
        }

        // Шаг 3: Удаляем таблицу support_tickets (теперь foreign key уже удален)
        if (Schema::hasTable('support_tickets')) {
            Schema::dropIfExists('support_tickets');
        }

        // Шаг 4: Удаляем ticket_chats (после удаления support_tickets)
        if (Schema::hasTable('ticket_chats')) {
            Schema::dropIfExists('ticket_chats');
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
