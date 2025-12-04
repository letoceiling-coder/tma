<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeaderboardPrize extends Model
{
    use HasFactory;

    protected $fillable = [
        'rank',
        'prize_amount',
        'prize_description',
        'is_active',
    ];

    protected $casts = [
        'rank' => 'integer',
        'prize_amount' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Получить приз за определенное место
     */
    public static function getPrizeForRank(int $rank): ?self
    {
        return static::where('rank', $rank)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Получить все активные призы
     */
    public static function getActivePrizes()
    {
        return static::where('is_active', true)
            ->orderBy('rank')
            ->get();
    }
}

