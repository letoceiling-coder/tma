<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelClick extends Model
{
    protected $fillable = [
        'channel_id',
        'user_id',
        'utm_params',
        'clicked_at',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
        'utm_params' => 'array',
    ];

    /**
     * Канал, по которому был клик
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Пользователь, который кликнул
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
