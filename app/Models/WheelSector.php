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
     * @param bool $testMode Режим тестирования (для детального логирования)
     * @return array ['sector' => WheelSector|null, 'random_value' => float, 'expected_result' => array]
     */
    public static function getRandomSector($testMode = false)
    {
        $sectors = static::getActiveSectors();
        
        if ($sectors->isEmpty()) {
            \Log::channel('wheel-errors')->warning('No active sectors found in getRandomSector');
            return [
                'sector' => null,
                'random_value' => null,
                'expected_result' => ['message' => 'No active sectors found'],
            ];
        }

        // Вычисляем сумму вероятностей всех активных секторов
        $totalProbability = $sectors->sum('probability_percent');
        
        // Детальное логирование для диагностики
        $sectorDetails = $sectors->map(function ($s) {
            return [
                'id' => $s->id,
                'sector_number' => $s->sector_number,
                'prize_type' => $s->prize_type,
                'probability' => (float) $s->probability_percent,
                'is_active' => $s->is_active,
            ];
        })->toArray();
        
        // Проверяем, что сумма не превышает 100% (с учетом погрешности округления)
        // Допускаем отклонение до 0.01% из-за погрешности чисел с плавающей точкой
        $epsilon = 0.01;
        if ($totalProbability > (100.0 + $epsilon)) {
            \Log::channel('wheel-errors')->error('Total probability exceeds 100%', [
                'total_probability' => $totalProbability,
                'sectors' => $sectorDetails,
                'difference' => $totalProbability - 100.0,
            ]);
            // В случае ошибки конфигурации возвращаем null
            return [
                'sector' => null,
                'random_value' => null,
                'expected_result' => [
                    'error' => 'Total probability exceeds 100%',
                    'total_probability' => $totalProbability,
                ],
            ];
        }
        
        // Если сумма немного превышает 100% из-за погрешности округления, нормализуем её
        if ($totalProbability > 100.0 && abs($totalProbability - 100.0) <= $epsilon) {
            // Нормализуем: используем 100.0 вместо фактической суммы
            $totalProbability = 100.0;
            if ($testMode || config('app.debug')) {
                \Log::info('Total probability normalized to 100% due to floating point precision', [
                    'original_total' => $sectors->sum('probability_percent'),
                    'normalized_total' => $totalProbability,
                ]);
            }
        }
        
        // Генерируем случайное число от 0 до 100 (включительно)
        // Используем более точный генератор случайных чисел
        $random = mt_rand(0, 10000) / 100.0; // 0.00 до 100.00 с точностью до 0.01
        
        // Детальное логирование для диагностики sponsor_gift
        if ($testMode || config('app.debug')) {
            \Log::info('Wheel sector selection - detailed', [
                'random_value' => $random,
                'total_probability' => $totalProbability,
                'sectors' => $sectorDetails,
                'empty_sector_probability' => 100.0 - $totalProbability,
            ]);
        }
        
        // Если сумма вероятностей < 100%, остаток трактуется как пустой сектор
        // Если random > totalProbability, возвращаем null (пустой сектор)
        if ($random > $totalProbability) {
            if ($testMode || config('app.debug')) {
                \Log::info('Empty sector selected (random > total_probability)', [
                    'random' => $random,
                    'total_probability' => $totalProbability,
                ]);
            }
            return [
                'sector' => null,
                'random_value' => $random,
                'expected_result' => [
                    'message' => 'Empty sector (random > total_probability)',
                    'random' => $random,
                    'total_probability' => $totalProbability,
                    'empty_probability' => 100.0 - $totalProbability,
                ],
            ];
        }
        
        // Распределяем секторы по последовательным диапазонам
        // Сектор i занимает диапазон: (cumulative_before, cumulative_before + probability_i]
        $cumulative = 0;
        $previousCumulative = 0;
        foreach ($sectors as $sector) {
            $previousCumulative = $cumulative;
            $cumulative += $sector->probability_percent;
            
            // Детальное логирование для каждого сектора
            if ($testMode || config('app.debug')) {
                \Log::debug('Checking sector range', [
                    'sector_id' => $sector->id,
                    'sector_number' => $sector->sector_number,
                    'prize_type' => $sector->prize_type,
                    'range_start' => $previousCumulative,
                    'range_end' => $cumulative,
                    'random' => $random,
                    'in_range' => $random <= $cumulative && $random > $previousCumulative,
                ]);
            }
            
            // Проверяем попадание в диапазон сектора
            // Используем строгое сравнение: random должен быть <= cumulative
            // и > предыдущего cumulative (что гарантируется последовательностью)
            if ($random <= $cumulative) {
                // Детальное логирование выбранного сектора
                $expectedResult = [
                    'sector_id' => $sector->id,
                    'sector_number' => $sector->sector_number,
                    'prize_type' => $sector->prize_type,
                    'prize_value' => $sector->prize_value,
                    'probability' => (float) $sector->probability_percent,
                    'range_start' => $previousCumulative,
                    'range_end' => $cumulative,
                    'random_in_range' => true,
                ];
                
                if ($testMode || config('app.debug')) {
                    \Log::info('Sector selected', array_merge($expectedResult, [
                        'random_value' => $random,
                    ]));
                }
                
                return [
                    'sector' => $sector,
                    'random_value' => $random,
                    'expected_result' => $expectedResult,
                ];
            }
        }

        // Fallback - вернуть последний сектор (не должно происходить при корректной логике)
        \Log::channel('wheel-errors')->warning('Fallback to last sector in getRandomSector', [
            'random' => $random,
            'total_probability' => $totalProbability,
            'sectors_count' => $sectors->count(),
            'sectors' => $sectorDetails,
        ]);
        
        $lastSector = $sectors->last();
        return [
            'sector' => $lastSector,
            'random_value' => $random,
            'expected_result' => [
                'message' => 'Fallback to last sector',
                'sector_id' => $lastSector->id ?? null,
                'sector_number' => $lastSector->sector_number ?? null,
                'prize_type' => $lastSector->prize_type ?? null,
            ],
        ];
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
        
        // Учитываем погрешность округления чисел с плавающей точкой
        // Допускаем отклонение до 0.01% (1e-14 в абсолютных единицах)
        $epsilon = 0.01;
        $diff = abs($totalProbability - 100.0);
        
        $result = [
            'valid' => true,
            'total' => (float) $totalProbability,
            'message' => '',
            'empty_sector_probability' => 100.0 - (float) $totalProbability,
        ];
        
        // Если разница больше допустимой погрешности и сумма превышает 100%
        if ($totalProbability > 100.0 && $diff > $epsilon) {
            $result['valid'] = false;
            $result['message'] = "Сумма вероятностей ({$totalProbability}%) превышает 100%";
        } elseif ($totalProbability < 0) {
            $result['valid'] = false;
            $result['message'] = "Сумма вероятностей ({$totalProbability}%) отрицательна";
        } elseif ($totalProbability < (100.0 - $epsilon)) {
            // Если сумма меньше 100% (с учетом погрешности)
            $emptyProb = 100.0 - (float) $totalProbability;
            $result['message'] = "Сумма вероятностей ({$totalProbability}%). Остаток ({$emptyProb}%) трактуется как вероятность пустого сектора.";
        } else {
            // Сумма равна 100% (с учетом погрешности округления)
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

