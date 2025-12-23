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
        'daily_tickets',
        'default_daily_tickets',
        'stars_per_ticket_purchase',
        'stars_enabled',
        'show_gift_button',
        'send_ticket_notification',
        'ticket_accrual_enabled',
        'ticket_accrual_interval_hours',
        'ticket_accrual_notifications_enabled',
        'broadcast_enabled',
        'broadcast_message_text',
        'broadcast_interval_hours',
        'broadcast_trigger',
        'welcome_text',
        'welcome_banner_url',
        'welcome_buttons',
    ];

    protected $casts = [
        'always_empty_mode' => 'boolean',
        'ticket_restore_hours' => 'integer',
        'leaderboard_period_months' => 'integer',
        'initial_tickets_count' => 'integer',
        'daily_tickets' => 'integer',
        'default_daily_tickets' => 'integer',
        'stars_per_ticket_purchase' => 'integer',
        'stars_enabled' => 'boolean',
        'show_gift_button' => 'boolean',
        'send_ticket_notification' => 'boolean',
        'ticket_accrual_enabled' => 'boolean',
        'ticket_accrual_interval_hours' => 'integer',
        'ticket_accrual_notifications_enabled' => 'boolean',
        'broadcast_enabled' => 'boolean',
        'broadcast_interval_hours' => 'integer',
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
                'daily_tickets' => 1, // По умолчанию 1 билет ежедневно
                'default_daily_tickets' => 1, // По умолчанию 1 билет для новых пользователей
                'stars_per_ticket_purchase' => 50, // По умолчанию 50 звёзд за покупку билетов
                'stars_enabled' => true, // По умолчанию Stars включены
                'show_gift_button' => false, // По умолчанию кнопка "Подарок" скрыта
                'send_ticket_notification' => true, // По умолчанию уведомления о билетах включены
                'ticket_accrual_enabled' => true, // По умолчанию автоматическое начисление билетов включено
                'ticket_accrual_interval_hours' => 24, // По умолчанию 24 часа
                'ticket_accrual_notifications_enabled' => true, // По умолчанию уведомления о начислении включены
                'broadcast_enabled' => true, // По умолчанию рассылка включена
                'broadcast_message_text' => 'Привет! У тебя есть новые возможности. Проверь приложение!', // По умолчанию текст сообщения
                'broadcast_interval_hours' => 24, // По умолчанию 24 часа после регистрации
                'broadcast_trigger' => 'after_registration', // По умолчанию триггер - после регистрации
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

