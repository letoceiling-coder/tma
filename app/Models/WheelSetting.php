<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WheelSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'always_empty_mode',
        'ticket_restore_hours',
        'leaderboard_period_months',
    ];

    protected $casts = [
        'always_empty_mode' => 'boolean',
        'ticket_restore_hours' => 'integer',
        'leaderboard_period_months' => 'integer',
    ];

    /**
     * Получить или создать единственную запись настроек
     */
    public static function getSettings(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'always_empty_mode' => false,
                'ticket_restore_hours' => 3, // По умолчанию 3 часа
                'leaderboard_period_months' => 1, // По умолчанию 1 месяц
            ]
        );
    }

    /**
     * Обновить настройки
     */
    public static function updateSettings(array $data): self
    {
        $settings = static::getSettings();
        $settings->update($data);
        return $settings;
    }
}

