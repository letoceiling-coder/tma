<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'username',
        'title',
        'external_url',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Получить активные каналы, отсортированные по приоритету
     */
    public static function getActiveChannels()
    {
        return static::where('is_active', true)
            ->orderBy('priority', 'desc')
            ->orderBy('id')
            ->get();
    }

    /**
     * Получить ссылку на канал (external_url или стандартная)
     */
    public function getChannelUrl(): string
    {
        return $this->external_url ?: "https://t.me/{$this->username}";
    }
}

