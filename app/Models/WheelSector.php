<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WheelSector extends Model
{
    use HasFactory;

    protected $fillable = [
        'sector_number',
        'prize_type',
        'prize_value',
        'icon_url',
        'probability_percent',
        'is_active',
        'prize_type_id',
    ];

    protected $casts = [
        'sector_number' => 'integer',
        'prize_value' => 'integer',
        'probability_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Получить активные секторы, отсортированные по номеру
     */
    public static function getActiveSectors()
    {
        return static::where('is_active', true)
            ->orderBy('sector_number')
            ->get();
    }

    /**
     * Выбрать случайный сектор на основе вероятностей
     * 
     * Логика:
     * - Генерируем случайное число от 0 до 100
     * - Секторы распределены по диапазонам пропорционально их вероятностям
     * - Если сумма вероятностей < 100%, остаток трактуется как "пустой сектор"
     * - Если сумма > 100%, это ошибка конфигурации (будет залогировано)
     * 
     * @return WheelSector|null
     */
    public static function getRandomSector()
    {
        $sectors = static::getActiveSectors();
        
        if ($sectors->isEmpty()) {
            return null;
        }

        // Вычисляем сумму вероятностей всех активных секторов
        $totalProbability = $sectors->sum('probability_percent');
        
        // Проверяем, что сумма не превышает 100%
        if ($totalProbability > 100.0) {
            \Log::channel('wheel-errors')->error('Total probability exceeds 100%', [
                'total_probability' => $totalProbability,
                'sectors' => $sectors->map(function ($s) {
                    return [
                        'id' => $s->id,
                        'sector_number' => $s->sector_number,
                        'probability' => $s->probability_percent,
                    ];
                })->toArray(),
            ]);
            // В случае ошибки конфигурации возвращаем null
            return null;
        }
        
        // Генерируем случайное число от 0 до 100 (включительно)
        // Используем более точный генератор случайных чисел
        $random = mt_rand(0, 10000) / 100.0; // 0.00 до 100.00 с точностью до 0.01
        
        // Если сумма вероятностей < 100%, остаток трактуется как пустой сектор
        // Если random > totalProbability, возвращаем null (пустой сектор)
        if ($random > $totalProbability) {
            return null; // Пустой сектор
        }
        
        // Распределяем секторы по последовательным диапазонам
        // Сектор i занимает диапазон: (cumulative_before, cumulative_before + probability_i]
        $cumulative = 0;
        foreach ($sectors as $sector) {
            $cumulative += $sector->probability_percent;
            // Проверяем попадание в диапазон сектора
            // Используем строгое сравнение: random должен быть <= cumulative
            // и > предыдущего cumulative (что гарантируется последовательностью)
            if ($random <= $cumulative) {
                return $sector;
            }
        }

        // Fallback - вернуть последний сектор (не должно происходить при корректной логике)
        \Log::channel('wheel-errors')->warning('Fallback to last sector in getRandomSector', [
            'random' => $random,
            'total_probability' => $totalProbability,
            'sectors_count' => $sectors->count(),
        ]);
        return $sectors->last();
    }
    
    /**
     * Проверить корректность конфигурации вероятностей
     * 
     * @return array ['valid' => bool, 'total' => float, 'message' => string]
     */
    public static function validateProbabilities()
    {
        $sectors = static::getActiveSectors();
        $totalProbability = $sectors->sum('probability_percent');
        
        $result = [
            'valid' => true,
            'total' => (float) $totalProbability,
            'message' => '',
            'empty_sector_probability' => 100.0 - (float) $totalProbability,
        ];
        
        if ($totalProbability > 100.0) {
            $result['valid'] = false;
            $result['message'] = "Сумма вероятностей ({$totalProbability}%) превышает 100%";
        } elseif ($totalProbability < 0) {
            $result['valid'] = false;
            $result['message'] = "Сумма вероятностей ({$totalProbability}%) отрицательна";
        } elseif ($totalProbability < 100.0) {
            $emptyProb = 100.0 - (float) $totalProbability;
            $result['message'] = "Сумма вероятностей ({$totalProbability}%). Остаток ({$emptyProb}%) трактуется как вероятность пустого сектора.";
        } else {
            $result['message'] = "Сумма вероятностей равна 100%. Все секторы имеют ненулевую вероятность.";
        }
        
        return $result;
    }

    /**
     * Связь с типом приза
     */
    public function prizeType()
    {
        return $this->belongsTo(PrizeType::class);
    }
}

