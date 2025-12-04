<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeaderboardSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'month',
        'year',
        'invites_count',
        'rank',
        'prize_amount',
        'prize_paid',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'invites_count' => 'integer',
        'rank' => 'integer',
        'prize_amount' => 'integer',
        'prize_paid' => 'boolean',
    ];

    /**
     * Связь с пользователем
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Получить ТОП пользователей за текущий месяц
     */
    public static function getTopUsers(int $limit = 10, ?int $month = null, ?int $year = null)
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        return static::where('month', $month)
            ->where('year', $year)
            ->orderBy('rank')
            ->limit($limit)
            ->with('user')
            ->get();
    }
}

