<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Folder;
use App\Models\Media;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImportWheelIcons extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wow:import-wheel-icons {--force : Принудительно переимпортировать все файлы, даже если записи существуют}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Импортировать иконки колеса из frontend/src/assets/wheel/ в папку media "общая"';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Начало импорта иконок колеса...');

        // Путь к иконкам на фронтенде
        $sourcePath = base_path('frontend/src/assets/wheel');
        
        if (!File::exists($sourcePath)) {
            $this->error("Директория не найдена: {$sourcePath}");
            return 1;
        }

        // Найти папку "общая" или "Общая" по имени или slug
        // Сначала ищем существующую папку "Общая" (slug: 'common') из миграции
        $folder = Folder::withoutUserScope()
            ->where(function($query) {
                $query->where('slug', 'common')
                      ->orWhere('slug', 'obshhaia')
                      ->orWhere('name', 'общая')
                      ->orWhere('name', 'Общая');
            })
            ->first();
        
        if (!$folder) {
            $this->info('Папка "общая" не найдена, создаем...');
            $folder = Folder::withoutUserScope()->create([
                'name' => 'общая',
                'slug' => 'obshhaia',
                'src' => 'folder',
                'parent_id' => null,
                'position' => 0,
            ]);
            $this->info('Папка "общая" создана (ID: ' . $folder->id . ')');
        } else {
            $this->info('Папка найдена (ID: ' . $folder->id . ', название: "' . $folder->name . '", slug: "' . $folder->slug . '")');
        }

        // Определяем путь к папке для сохранения файлов
        // Используем slug папки для пути (common или obshhaia)
        $folderSlug = $folder->slug ?: Str::slug($folder->name);
        $targetPath = public_path('upload/' . $folderSlug);
        
        if (!File::exists($targetPath)) {
            File::makeDirectory($targetPath, 0755, true);
            $this->info("Создана директория: {$targetPath}");
        } else {
            $this->info("Директория существует: {$targetPath}");
        }

        // Получить все PNG файлы из директории иконок
        $iconFiles = File::glob($sourcePath . '/*.png');
        
        if (empty($iconFiles)) {
            $this->warn('Не найдено PNG файлов в директории иконок');
            return 0;
        }

        $this->info('Найдено файлов: ' . count($iconFiles));
        $this->newLine();

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($iconFiles as $sourceFile) {
            $fileName = File::name($sourceFile);
            $extension = File::extension($sourceFile);
            $originalName = File::basename($sourceFile);
            
            // Проверяем, не существует ли уже файл с таким именем в папке "общая"
            $existingMedia = Media::withoutUserScope()
                ->where('folder_id', $folder->id)
                ->where('original_name', $originalName)
                ->first();

            // Если запись существует и не режим force, проверяем физическое наличие файла
            if ($existingMedia && !$this->option('force')) {
                // Получаем путь к файлу из metadata или из disk/name
                $metadata = json_decode($existingMedia->metadata, true);
                $filePath = $metadata['path'] ?? ($existingMedia->disk . '/' . $existingMedia->name);
                $fullPath = public_path($filePath);
                
                // Если файл физически существует, пропускаем
                if (File::exists($fullPath)) {
                    $this->warn("  Пропущен: {$originalName} (файл уже существует)");
                    $skipped++;
                    continue;
                } else {
                    // Если файла нет, удаляем старую запись
                    $this->info("  Удаление старой записи для {$originalName} (файл не найден)...");
                    try {
                        $existingMedia->delete();
                    } catch (\Exception $e) {
                        $this->warn("  Не удалось удалить запись: " . $e->getMessage());
                    }
                }
            } elseif ($existingMedia && $this->option('force')) {
                // Принудительная перезапись - удаляем старую запись
                $this->info("  Удаление существующей записи для {$originalName} (режим --force)...");
                try {
                    // Удаляем физический файл
                    $metadata = json_decode($existingMedia->metadata, true);
                    $filePath = $metadata['path'] ?? ($existingMedia->disk . '/' . $existingMedia->name);
                    $fullPath = public_path($filePath);
                    if (File::exists($fullPath)) {
                        File::delete($fullPath);
                    }
                    $existingMedia->delete();
                } catch (\Exception $e) {
                    $this->warn("  Не удалось удалить запись: " . $e->getMessage());
                }
            }

            // Генерируем уникальное имя файла
            $uniqueFileName = uniqid() . '_' . time() . '.' . $extension;
            $targetFile = $targetPath . '/' . $uniqueFileName;

            // Копируем файл
            if (!File::copy($sourceFile, $targetFile)) {
                $this->error("  Ошибка копирования: {$originalName}");
                $errors++;
                continue;
            }

            // Получаем размер файла
            $fileSize = File::size($targetFile);

            // Получаем размеры изображения
            $width = null;
            $height = null;
            $imageInfo = @getimagesize($targetFile);
            if ($imageInfo !== false) {
                $width = $imageInfo[0];
                $height = $imageInfo[1];
            }

            // Создаем запись в базе данных
            $relativePath = 'upload/' . $folderSlug . '/' . $uniqueFileName;
            
            try {
                $media = Media::withoutUserScope()->create([
                    'name' => $uniqueFileName,
                    'original_name' => $originalName,
                    'extension' => $extension,
                    'disk' => 'upload/' . $folderSlug,
                    'width' => $width,
                    'height' => $height,
                    'type' => 'photo',
                    'size' => $fileSize,
                    'folder_id' => $folder->id,
                    'user_id' => null, // Без привязки к пользователю
                    'temporary' => false,
                    'metadata' => json_encode([
                        'path' => $relativePath,
                        'mime_type' => 'image/png'
                    ])
                ]);

                $this->info("  ✓ Импортирован: {$originalName} (ID: {$media->id})");
                $imported++;
            } catch (\Exception $e) {
                $this->error("  ✗ Ошибка создания записи для {$originalName}: " . $e->getMessage());
                // Удаляем скопированный файл при ошибке
                if (File::exists($targetFile)) {
                    File::delete($targetFile);
                }
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Импорт завершен!");
        $this->info("  Импортировано: {$imported}");
        $this->info("  Пропущено: {$skipped}");
        if ($errors > 0) {
            $this->warn("  Ошибок: {$errors}");
        }

        // Выводим информацию о папке
        $this->newLine();
        $this->info("Папка: {$folder->name} (ID: {$folder->id}, slug: {$folder->slug})");
        $this->info("Путь к файлам: upload/{$folderSlug}/");
        
        // Проверяем количество файлов в папке
        $filesInFolder = Media::withoutUserScope()
            ->where('folder_id', $folder->id)
            ->count();
        $this->info("Файлов в БД для этой папки: {$filesInFolder}");

        return 0;
    }
}
