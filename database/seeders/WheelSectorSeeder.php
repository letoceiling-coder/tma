<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WheelSector;
use App\Models\Folder;
use App\Models\Media;
use Illuminate\Support\Facades\DB;

class WheelSectorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Создаем или обновляем 12 секторов с начальными данными
        // Используем updateOrCreate чтобы seeder был идемпотентным
        // и не требовал truncate (который не работает с внешними ключами)
        // 
        // Соответствие с фронтендом (MainWheel.tsx wheelSegments):
        // sector 1: 0 -> empty
        // sector 2: 2000 -> money (2000)
        // sector 3: 0 -> empty
        // sector 4: 300 -> money (300)
        // sector 5: 500 -> money (500)
        // sector 6: 0 -> empty
        // sector 7: 0 -> empty
        // sector 8: 300 -> money (300)
        // sector 9: 300 -> money (300)
        // sector 10: 0 -> empty
        // sector 11: 1000 -> money (1000)
        // sector 12: 0 -> empty
        $sectors = [
            [
                'sector_number' => 1,
                'prize_type' => 'empty',
                'prize_value' => 0,
                'probability_percent' => 8.33, // 1/12 ≈ 8.33%
                'is_active' => true,
                'icon_url' => null, // Будет установлена через админку или команду импорта
            ],
            [
                'sector_number' => 2,
                'prize_type' => 'money',
                'prize_value' => 2000,
                'probability_percent' => 8.33,
                'is_active' => true,
                'icon_url' => null,
            ],
            [
                'sector_number' => 3,
                'prize_type' => 'empty',
                'prize_value' => 0,
                'probability_percent' => 8.33,
                'is_active' => true,
                'icon_url' => null,
            ],
            [
                'sector_number' => 4,
                'prize_type' => 'money',
                'prize_value' => 300,
                'probability_percent' => 8.33,
                'is_active' => true,
                'icon_url' => null,
            ],
            [
                'sector_number' => 5,
                'prize_type' => 'money',
                'prize_value' => 500,
                'probability_percent' => 8.33,
                'is_active' => true,
                'icon_url' => null,
            ],
            [
                'sector_number' => 6,
                'prize_type' => 'empty',
                'prize_value' => 0,
                'probability_percent' => 8.33,
                'is_active' => true,
                'icon_url' => null,
            ],
            [
                'sector_number' => 7,
                'prize_type' => 'empty',
                'prize_value' => 0,
                'probability_percent' => 8.33,
                'is_active' => true,
                'icon_url' => null,
            ],
            [
                'sector_number' => 8,
                'prize_type' => 'money',
                'prize_value' => 300,
                'probability_percent' => 8.33,
                'is_active' => true,
                'icon_url' => null,
            ],
            [
                'sector_number' => 9,
                'prize_type' => 'money',
                'prize_value' => 300,
                'probability_percent' => 8.33,
                'is_active' => true,
                'icon_url' => null,
            ],
            [
                'sector_number' => 10,
                'prize_type' => 'empty',
                'prize_value' => 0,
                'probability_percent' => 8.33,
                'is_active' => true,
                'icon_url' => null,
            ],
            [
                'sector_number' => 11,
                'prize_type' => 'money',
                'prize_value' => 1000,
                'probability_percent' => 8.33,
                'is_active' => true,
                'icon_url' => null,
            ],
            [
                'sector_number' => 12,
                'prize_type' => 'empty',
                'prize_value' => 0,
                'probability_percent' => 8.34, // Чуть больше чтобы сумма была 100%
                'is_active' => true,
                'icon_url' => null,
            ],
        ];

        // Маппинг иконок по типу приза и значению
        // Ищем иконки в папке "общая" по оригинальному имени
        $iconMapping = $this->getIconMapping();

        foreach ($sectors as $sector) {
            // Определяем, какая иконка нужна для этого сектора
            $iconUrl = $this->getIconUrlForSector($sector, $iconMapping);
            
            $sectorData = $sector;
            if ($iconUrl) {
                $sectorData['icon_url'] = $iconUrl;
            }

            WheelSector::updateOrCreate(
                ['sector_number' => $sector['sector_number']],
                $sectorData
            );
        }

        $this->command->info('Создано/обновлено 12 секторов рулетки');
        
        // Выводим информацию о присвоенных иконках
        $sectorsWithIcons = WheelSector::whereNotNull('icon_url')->count();
        $iconsFound = count($iconMapping);
        
        if ($iconsFound > 0) {
            $this->command->info("  Найдено иконок в media: {$iconsFound}");
        } else {
            $this->command->warn("  Внимание: иконки не найдены в media. Выполните: php artisan wow:import-wheel-icons");
        }
        
        if ($sectorsWithIcons > 0) {
            $this->command->info("  Секторов с иконками: {$sectorsWithIcons}/12");
        } else {
            $this->command->warn("  Секторы созданы, но иконки не присвоены. Импортируйте иконки: php artisan wow:import-wheel-icons");
        }
    }

    /**
     * Получить маппинг иконок из media
     */
    private function getIconMapping(): array
    {
        // Найти папку "общая" или "Общая"
        $folder = Folder::withoutUserScope()
            ->where(function($query) {
                $query->where('slug', 'common')
                      ->orWhere('slug', 'obshhaia')
                      ->orWhere('name', 'общая')
                      ->orWhere('name', 'Общая');
            })
            ->first();

        if (!$folder) {
            return [];
        }

        // Получаем все иконки из папки
        $mediaFiles = Media::withoutUserScope()
            ->where('folder_id', $folder->id)
            ->where('extension', 'png')
            ->get();

        $mapping = [];
        foreach ($mediaFiles as $media) {
            $metadata = json_decode($media->metadata, true);
            $url = '/' . ($metadata['path'] ?? ($media->disk . '/' . $media->name));
            $mapping[$media->original_name] = $url;
        }

        return $mapping;
    }

    /**
     * Получить URL иконки для сектора на основе типа приза и значения
     */
    private function getIconUrlForSector(array $sector, array $iconMapping): ?string
    {
        $prizeType = $sector['prize_type'] ?? null;
        $prizeValue = $sector['prize_value'] ?? 0;

        // Определяем имя иконки на основе типа приза
        $iconName = null;

        switch ($prizeType) {
            case 'empty':
                $iconName = 'prize-0.png';
                break;
            case 'money':
                if ($prizeValue == 300) {
                    $iconName = 'prize-300.png';
                } elseif ($prizeValue == 500) {
                    $iconName = 'prize-500.png';
                } elseif ($prizeValue == 1000) {
                    $iconName = 'prize-500.png'; // Можно использовать другую иконку для 1000
                } elseif ($prizeValue == 2000) {
                    $iconName = 'prize-wow.png';
                } else {
                    $iconName = 'prize-0.png';
                }
                break;
            case 'ticket':
                $iconName = 'prize-ticket.png';
                break;
            case 'secret_box':
                $iconName = 'prize-secret.png';
                break;
            default:
                $iconName = 'prize-0.png';
                break;
        }

        // Возвращаем URL иконки из маппинга
        return $iconMapping[$iconName] ?? null;
    }
}

