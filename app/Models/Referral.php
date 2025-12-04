<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Referral extends Model
{
    use HasFactory;

    protected $fillable = [
        'inviter_id',
        'invited_id',
        'invited_at',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
    ];

    /**
     * Связь с пригласившим пользователем
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    /**
     * Связь с приглашенным пользователем
     */
    public function invited(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_id');
    }

    /**
     * Получить количество приглашений пользователя за текущий месяц
     */
    public static function getInvitesCountForUser(int $userId, ?int $month = null, ?int $year = null): int
    {
        $query = static::where('inviter_id', $userId);
        
        if ($month && $year) {
            $query->whereYear('invited_at', $year)
                  ->whereMonth('invited_at', $month);
        }
        
        return $query->count();
    }
}

