<?php

namespace App\Console\Commands;

use App\Models\SupportTicket;
use App\Models\SupportMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class ClearAllTickets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:clear 
                            {--force : Force clear without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Очистить все тикеты поддержки, сообщения и файлы';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Получаем статистику до очистки
        $totalSupportTickets = SupportTicket::count();
        $totalSupportMessages = SupportMessage::count();

        $this->info('=== Статистика до очистки ===');
        $this->line("Тикетов поддержки: {$totalSupportTickets}");
        $this->line("Сообщений поддержки: {$totalSupportMessages}");
        $this->newLine();

        // Подтверждение, если не указан флаг --force
        if (!$this->option('force')) {
            if (!$this->confirm('Вы уверены, что хотите очистить все тикеты поддержки? Это действие необратимо!')) {
                $this->warn('Операция отменена.');
                return Command::FAILURE;
            }
        }

        $this->info('Начинаю очистку тикетов поддержки...');

        try {
            // Получаем все сообщения с attachments перед удалением
            $messagesWithAttachments = SupportMessage::whereNotNull('attachments')->get();
            $attachmentsToDelete = [];
            
            foreach ($messagesWithAttachments as $message) {
                $attachments = $message->attachments ?? [];
                if (is_array($attachments)) {
                    foreach ($attachments as $attachment) {
                        if (isset($attachment['path'])) {
                            $attachmentsToDelete[] = $attachment['path'];
                        }
                        if (isset($attachment['url'])) {
                            // Извлекаем путь из URL
                            $urlPath = parse_url($attachment['url'], PHP_URL_PATH);
                            if ($urlPath) {
                                $attachmentsToDelete[] = ltrim($urlPath, '/');
                            }
                        }
                    }
                }
            }
            
            // Удаляем физические файлы
            $deletedFiles = 0;
            foreach ($attachmentsToDelete as $filePath) {
                try {
                    // Нормализуем путь
                    $normalizedPath = ltrim($filePath, '/');
                    
                    // Пробуем удалить через Storage
                    if (Storage::disk('public')->exists($normalizedPath)) {
                        Storage::disk('public')->delete($normalizedPath);
                        $deletedFiles++;
                    } elseif (File::exists(public_path($normalizedPath))) {
                        File::delete(public_path($normalizedPath));
                        $deletedFiles++;
                    } elseif (File::exists(storage_path('app/public/' . $normalizedPath))) {
                        File::delete(storage_path('app/public/' . $normalizedPath));
                        $deletedFiles++;
                    }
                } catch (\Exception $e) {
                    // Игнорируем ошибки удаления файлов
                    $this->warn("Не удалось удалить файл: {$filePath} - " . $e->getMessage());
                }
            }
            
            // Также удаляем всю папку support/attachments, если она существует
            try {
                $supportAttachmentsPath = storage_path('app/public/support/attachments');
                if (File::exists($supportAttachmentsPath) && File::isDirectory($supportAttachmentsPath)) {
                    File::deleteDirectory($supportAttachmentsPath);
                    $this->info("✓ Удалена папка support/attachments");
                }
            } catch (\Exception $e) {
                $this->warn("Не удалось удалить папку support/attachments: " . $e->getMessage());
            }
            
            if ($deletedFiles > 0) {
                $this->info("✓ Удалено файлов вложений: {$deletedFiles}");
            } else {
                $this->info("✓ Файлы вложений не найдены или уже удалены");
            }
            
            // Удаляем все тикеты поддержки (сообщения удалятся автоматически через cascade)
            $supportTicketsCount = SupportTicket::count();
            SupportTicket::truncate(); // Truncate автоматически коммитит
            
            $this->info("✓ Удалено тикетов поддержки: {$supportTicketsCount}");
            $this->info("✓ Удалено сообщений поддержки (через cascade)");

            $this->newLine();
            $this->info('=== Статистика после очистки ===');
            $this->line("Тикетов поддержки: " . SupportTicket::count());
            $this->line("Сообщений поддержки: " . SupportMessage::count());

            $this->newLine();
            $this->info('✓ Все тикеты поддержки успешно очищены!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Ошибка при очистке тикетов: ' . $e->getMessage());
            $this->error('Трассировка: ' . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
