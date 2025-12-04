<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Folder;
use App\Models\Media;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ImportWheelIcons extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wow:import-wheel-icons';

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

        // Найти или создать папку "общая"
        $folder = Folder::withoutUserScope()->where('name', 'общая')->first();
        
        if (!$folder) {
            $this->info('Папка "общая" не найдена, создаем...');
            $folder = Folder::withoutUserScope()->create([
                'name' => 'общая',
                'slug' => 'obshhaia',
                'parent_id' => null,
                'position' => 0,
                'user_id' => null,
            ]);
            $this->info('Папка "общая" создана (ID: ' . $folder->id . ')');
        } else {
            $this->info('Папка "общая" найдена (ID: ' . $folder->id . ')');
        }

        // Получить путь к папке "общая" используя метод getFolderPath
        $folderPath = $this->getFolderPath($folder);
        $targetPath = public_path('upload/' . $folderPath);
        if (!File::exists($targetPath)) {
            File::makeDirectory($targetPath, 0755, true);
            $this->info("Создана директория: {$targetPath}");
        }

        // Получить все PNG файлы из директории иконок
        $iconFiles = File::glob($sourcePath . '/*.png');
        
        if (empty($iconFiles)) {
            $this->warn('Не найдено PNG файлов в директории иконок');
            return 0;
        }

        $this->info('Найдено файлов: ' . count($iconFiles));

        $imported = 0;
        $skipped = 0;

        foreach ($iconFiles as $sourceFile) {
            $fileName = File::name($sourceFile);
            $extension = File::extension($sourceFile);
            $originalName = File::basename($sourceFile);
            
            // Проверяем, не существует ли уже файл с таким именем в папке "общая"
            $existingMedia = Media::where('folder_id', $folder->id)
                ->where('original_name', $originalName)
                ->first();

            if ($existingMedia) {
                $this->warn("  Пропущен: {$originalName} (уже существует)");
                $skipped++;
                continue;
            }

            // Генерируем уникальное имя файла
            $uniqueFileName = uniqid() . '_' . time() . '.' . $extension;
            $targetFile = $targetPath . '/' . $uniqueFileName;

            // Копируем файл
            if (!File::copy($sourceFile, $targetFile)) {
                $this->error("  Ошибка копирования: {$originalName}");
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
            $relativePath = 'upload/' . $folderPath . '/' . $uniqueFileName;
            
            try {
                Media::withoutUserScope()->create([
                    'name' => $uniqueFileName,
                    'original_name' => $originalName,
                    'extension' => $extension,
                    'disk' => 'upload/' . $folderPath,
                    'width' => $width,
                    'height' => $height,
                    'type' => 'photo',
                    'size' => $fileSize,
                    'folder_id' => $folder->id,
                    'user_id' => null,
                    'temporary' => false,
                    'metadata' => json_encode([
                        'path' => $relativePath,
                        'mime_type' => 'image/png'
                    ])
                ]);

                $this->info("  ✓ Импортирован: {$originalName}");
                $imported++;
            } catch (\Exception $e) {
                $this->error("  ✗ Ошибка создания записи для {$originalName}: " . $e->getMessage());
                // Удаляем скопированный файл при ошибке
                if (File::exists($targetFile)) {
                    File::delete($targetFile);
                }
            }
        }

        $this->newLine();
        $this->info("Импорт завершен!");
        $this->info("  Импортировано: {$imported}");
        $this->info("  Пропущено: {$skipped}");

        return 0;
    }

    /**
     * Получить путь к папке из иерархии (как в MediaController)
     */
    private function getFolderPath(Folder $folder): string
    {
        $path = [];
        $currentFolder = $folder;

        // Загружаем родителей для построения пути
        while ($currentFolder) {
            array_unshift($path, Str::slug($currentFolder->name));
            $currentFolder = $currentFolder->parent;
        }

        return implode('/', $path);
    }
}

