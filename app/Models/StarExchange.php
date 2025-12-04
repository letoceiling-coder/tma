<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StarExchange extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'stars_amount',
        'tickets_received',
        'transaction_id',
        'status',
    ];

    protected $casts = [
        'stars_amount' => 'integer',
        'tickets_received' => 'integer',
    ];

    /**
     * Связь с пользователем
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

