<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentError extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'error_type',
        'error_message',
        'request_payload',
        'response_code',
        'response_data',
        'payment_id',
        'timestamp',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_data' => 'array',
        'timestamp' => 'datetime',
    ];

    /**
     * Связь с пользователем
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Логировать ошибку платежа
     */
    public static function logError(
        string $errorType,
        string $errorMessage,
        ?int $userId = null,
        ?array $requestPayload = null,
        ?int $responseCode = null,
        ?array $responseData = null,
        ?string $paymentId = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'error_type' => $errorType,
            'error_message' => $errorMessage,
            'request_payload' => $requestPayload,
            'response_code' => $responseCode,
            'response_data' => $responseData,
            'payment_id' => $paymentId,
            'timestamp' => now(),
        ]);
    }
}
