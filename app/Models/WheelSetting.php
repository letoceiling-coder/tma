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
        'admin_username',
        'initial_tickets_count',
        'stars_per_ticket_purchase',
        'welcome_text',
        'welcome_banner_url',
        'welcome_buttons',
    ];

    protected $casts = [
        'always_empty_mode' => 'boolean',
        'ticket_restore_hours' => 'integer',
        'leaderboard_period_months' => 'integer',
        'initial_tickets_count' => 'integer',
        'stars_per_ticket_purchase' => 'integer',
        'welcome_buttons' => 'array',
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
                'initial_tickets_count' => 1, // По умолчанию 1 билет
                'stars_per_ticket_purchase' => 50, // По умолчанию 50 звёзд за покупку билетов
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

    /**
     * Получить валидное значение стартовых билетов
     * Если значение не задано, пустое, > 100 или < 0 — возвращает дефолтное значение 1
     * 
     * @return int
     */
    public function getValidStartTickets(): int
    {
        $startTickets = $this->initial_tickets_count;
        
        // Если значение не задано, пустое или null — возвращаем 1
        if ($startTickets === null || $startTickets === '') {
            return 1;
        }
        
        // Преобразуем в integer для проверки
        $startTickets = (int) $startTickets;
        
        // Если значение > 100 или < 0 — сбрасываем к 1
        if ($startTickets > 100 || $startTickets < 0) {
            return 1;
        }
        
        return $startTickets;
    }

    /**
     * Получить валидное значение количества звёзд за покупку билетов
     * Если значение не задано, пустое, <= 0 или > 10000 — возвращает дефолтное значение 50
     * 
     * @return int
     */
    public function getValidStarsPerTicketPurchase(): int
    {
        $starsAmount = $this->stars_per_ticket_purchase;
        
        // Если значение не задано, пустое или null — возвращаем 50
        if ($starsAmount === null || $starsAmount === '') {
            return 50;
        }
        
        // Преобразуем в integer для проверки
        $starsAmount = (int) $starsAmount;
        
        // Если значение <= 0 или > 10000 — сбрасываем к 50
        if ($starsAmount <= 0 || $starsAmount > 10000) {
            return 50;
        }
        
        return $starsAmount;
    }
}

