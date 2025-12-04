<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WheelSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'always_empty_mode',
    ];

    protected $casts = [
        'always_empty_mode' => 'boolean',
    ];

    /**
     * Получить или создать единственную запись настроек
     */
    public static function getSettings(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            ['always_empty_mode' => false]
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

