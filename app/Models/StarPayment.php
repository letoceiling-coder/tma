<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StarPayment extends Model
{
    use HasFactory;

    /**
     * Название таблицы в БД
     */
    protected $table = 'stars_payments';

    protected $fillable = [
        'user_id',
        'amount',
        'purpose',
        'status',
        'payment_id',
        'invoice_url',
        'payload',
        'telegram_response',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'payload' => 'array',
        'telegram_response' => 'array',
        'paid_at' => 'datetime',
    ];

    /**
     * Связь с пользователем
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
