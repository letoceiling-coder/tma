<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Spin extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'spin_time',
        'prize_type',
        'prize_value',
        'prize_name',
        'sector_id',
        'sector_number',
        'external_gift_id',
        'sponsor_name',
        'delivery_status',
    ];

    protected $casts = [
        'spin_time' => 'datetime',
        'prize_value' => 'integer',
        'sector_number' => 'integer',
    ];

    /**
     * Связь с пользователем
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Связь с сектором рулетки
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(WheelSector::class);
    }
}

