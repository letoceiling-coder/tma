<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PrizeType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'value',
        'message',
        'action',
        'icon_url',
        'is_active',
    ];

    protected $casts = [
        'value' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Получить активные типы призов
     */
    public static function getActive()
    {
        return static::where('is_active', true)->get();
    }

    /**
     * Получить типы призов по типу
     */
    public static function getByType(string $type)
    {
        return static::where('type', $type)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Связь с секторами рулетки
     */
    public function wheelSectors()
    {
        return $this->hasMany(WheelSector::class);
    }
}

