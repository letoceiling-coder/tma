<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrizeIssueLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sector_id',
        'prize_type',
        'error_message',
        'trace_id',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    /**
     * Связь с пользователем
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Связь с сектором
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(WheelSector::class);
    }

    /**
     * Логировать ошибку выдачи приза
     */
    public static function logError(
        int $userId = null,
        int $sectorId = null,
        string $prizeType = null,
        string $errorMessage,
        string $traceId = null,
        array $context = null
    ): self {
        return static::create([
            'user_id' => $userId,
            'sector_id' => $sectorId,
            'prize_type' => $prizeType,
            'error_message' => $errorMessage,
            'trace_id' => $traceId ?? uniqid('prize_error_', true),
            'context' => $context,
        ]);
    }
}
