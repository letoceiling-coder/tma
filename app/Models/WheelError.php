<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WheelError extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sector_id',
        'prize_type',
        'random_value',
        'expected_sector_result',
        'error_type',
        'error_message',
        'sector_config_snapshot',
        'request_payload',
        'timestamp',
    ];

    protected $casts = [
        'sector_config_snapshot' => 'array',
        'request_payload' => 'array',
        'expected_sector_result' => 'array',
        'random_value' => 'decimal:2',
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
     * Связь с сектором
     */
    public function sector()
    {
        return $this->belongsTo(WheelSector::class);
    }

    /**
     * Логировать ошибку рулетки
     * 
     * @param string $errorType Тип ошибки
     * @param string $errorMessage Сообщение об ошибке
     * @param array $context Дополнительный контекст
     * @param int|null $userId ID пользователя
     * @param int|null $sectorId ID сектора
     * @param string|null $prizeType Тип приза
     * @param float|null $randomValue Случайное значение, использованное при выборе сектора
     * @param array|null $expectedSectorResult Ожидаемый результат выбора сектора
     * @return WheelError
     */
    public static function logError(
        string $errorType,
        string $errorMessage,
        array $context = [],
        ?int $userId = null,
        ?int $sectorId = null,
        ?string $prizeType = null,
        ?float $randomValue = null,
        ?array $expectedSectorResult = null
    ): self {
        // Получаем снимок конфигурации секторов
        $sectorConfig = WheelSector::getActiveSectors()->map(function ($sector) {
            return [
                'id' => $sector->id,
                'sector_number' => $sector->sector_number,
                'prize_type' => $sector->prize_type,
                'prize_value' => $sector->prize_value,
                'probability_percent' => (float) $sector->probability_percent,
                'is_active' => $sector->is_active,
            ];
        })->toArray();

        return self::create([
            'user_id' => $userId,
            'sector_id' => $sectorId,
            'prize_type' => $prizeType,
            'random_value' => $randomValue,
            'expected_sector_result' => $expectedSectorResult,
            'error_type' => $errorType,
            'error_message' => $errorMessage,
            'sector_config_snapshot' => $sectorConfig,
            'request_payload' => $context,
            'timestamp' => now(),
        ]);
    }
}
