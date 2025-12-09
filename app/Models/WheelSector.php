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
     */
    public static function getRandomSector()
    {
        $sectors = static::getActiveSectors();
        
        if ($sectors->isEmpty()) {
            return null;
        }

        $totalProbability = $sectors->sum('probability_percent');
        $random = mt_rand(0, (int)($totalProbability * 100)) / 100;
        
        $cumulative = 0;
        foreach ($sectors as $sector) {
            $cumulative += $sector->probability_percent;
            if ($random <= $cumulative) {
                return $sector;
            }
        }

        // Fallback - вернуть последний сектор
        return $sectors->last();
    }

    /**
     * Связь с типом приза
     */
    public function prizeType()
    {
        return $this->belongsTo(PrizeType::class);
    }
}

