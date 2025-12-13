<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserTicket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearAllTickets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:clear 
                            {--force : Force clear without confirmation}
                            {--history : Also clear user_tickets history table}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Очистить все тикеты у всех пользователей';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Получаем статистику до очистки
        $totalUsers = User::count();
        $usersWithTickets = User::where('tickets_available', '>', 0)->count();
        $totalTickets = User::sum('tickets_available');
        $totalHistoryRecords = UserTicket::count();

        $this->info('=== Статистика до очистки ===');
        $this->line("Всего пользователей: {$totalUsers}");
        $this->line("Пользователей с тикетами: {$usersWithTickets}");
        $this->line("Всего тикетов: {$totalTickets}");
        $this->line("Записей в истории: {$totalHistoryRecords}");
        $this->newLine();

        // Подтверждение, если не указан флаг --force
        if (!$this->option('force')) {
            if (!$this->confirm('Вы уверены, что хотите очистить все тикеты? Это действие необратимо!')) {
                $this->warn('Операция отменена.');
                return Command::FAILURE;
            }
        }

        $this->info('Начинаю очистку тикетов...');

        try {
            DB::beginTransaction();

            // Очищаем tickets_available у всех пользователей
            $clearedCount = User::where('tickets_available', '>', 0)
                ->update(['tickets_available' => 0]);

            $this->info("✓ Очищено тикетов у {$clearedCount} пользователей");

            // Также сбрасываем связанные поля
            User::whereNotNull('tickets_depleted_at')
                ->update(['tickets_depleted_at' => null]);
            
            User::whereNotNull('referral_popup_shown_at')
                ->update(['referral_popup_shown_at' => null]);

            $this->info("✓ Сброшены связанные поля (tickets_depleted_at, referral_popup_shown_at)");

            DB::commit();

            // Очищаем историю ПОСЛЕ коммита транзакции (truncate не работает внутри транзакции)
            if ($this->option('history')) {
                // Truncate автоматически коммитит, поэтому делаем вне транзакции
                UserTicket::truncate();
                $this->info("✓ Очищена история тикетов (таблица user_tickets)");
            }

            $this->newLine();
            $this->info('=== Статистика после очистки ===');
            $this->line("Пользователей с тикетами: " . User::where('tickets_available', '>', 0)->count());
            $this->line("Всего тикетов: " . User::sum('tickets_available'));
            if ($this->option('history')) {
                $this->line("Записей в истории: " . UserTicket::count());
            }

            $this->newLine();
            $this->info('✓ Все тикеты успешно очищены!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Ошибка при очистке тикетов: ' . $e->getMessage());
            $this->error('Трассировка: ' . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
