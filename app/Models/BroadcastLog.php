<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastLog extends Model
{
    protected $fillable = [
        'user_id',
        'message_text',
        'sent_at',
        'status',
        'error_message',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /**
     * Пользователь, которому было отправлено сообщение
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
