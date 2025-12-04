<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tickets_count',
        'restored_at',
        'source',
    ];

    protected $casts = [
        'tickets_count' => 'integer',
        'restored_at' => 'datetime',
    ];

    /**
     * Связь с пользователем
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Получить количество доступных билетов пользователя
     */
    public static function getAvailableTicketsCount(int $userId): int
    {
        return static::where('user_id', $userId)
            ->where(function($query) {
                $query->where('restored_at', '<=', now())
                      ->orWhereNull('restored_at');
            })
            ->sum('tickets_count');
    }
}

