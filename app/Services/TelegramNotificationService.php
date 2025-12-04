<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    /**
     * Отправить уведомление пользователю через Telegram Bot API
     * 
     * @param int $telegramId
     * @param string $message
     * @param array $options
     * @return bool
     */
    public static function sendNotification(int $telegramId, string $message, array $options = []): bool
    {
        $botToken = config('services.telegram.bot_token');
        
        if (!$botToken) {
            Log::warning('Telegram bot token not configured, cannot send notification');
            return false;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $telegramId,
                'text' => $message,
                'parse_mode' => $options['parse_mode'] ?? 'HTML',
                'disable_web_page_preview' => $options['disable_web_page_preview'] ?? true,
            ]);

            if ($response->successful()) {
                Log::info('Telegram notification sent', [
                    'telegram_id' => $telegramId,
                ]);
                return true;
            } else {
                Log::error('Failed to send Telegram notification', [
                    'telegram_id' => $telegramId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Error sending Telegram notification', [
                'telegram_id' => $telegramId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Уведомление о новом билете
     * 
     * @param User $user
     * @return bool
     */
    public static function notifyNewTicket(User $user): bool
    {
        if (!$user->telegram_id) {
            return false;
        }

        $message = "🎫 <b>Новый билет доступен!</b>\n\n";
        $message .= "У вас теперь {$user->tickets_available} билет(ов).\n";
        $message .= "Крутите колесо и выигрывайте призы! 🎰";

        return self::sendNotification($user->telegram_id, $message);
    }

    /**
     * Уведомление о выигрыше
     * 
     * @param User $user
     * @param int $prizeValue
     * @param string $prizeType
     * @return bool
     */
    public static function notifyWin(User $user, int $prizeValue, string $prizeType): bool
    {
        if (!$user->telegram_id) {
            return false;
        }

        $message = "🎉 <b>Поздравляем с выигрышем!</b>\n\n";
        
        if ($prizeType === 'money') {
            $message .= "Вы выиграли <b>{$prizeValue} ₽</b>! 💰";
        } elseif ($prizeType === 'ticket') {
            $message .= "Вы выиграли <b>{$prizeValue} билет(ов)</b>! 🎫";
        } elseif ($prizeType === 'secret_box') {
            $message .= "Вы выиграли <b>Секретный бокс</b>! 🎁";
        }

        return self::sendNotification($user->telegram_id, $message);
    }

    /**
     * Напоминание о прокрутах
     * 
     * @param User $user
     * @return bool
     */
    public static function notifyReminder(User $user): bool
    {
        if (!$user->telegram_id) {
            return false;
        }

        if ($user->tickets_available <= 0) {
            return false; // Не отправляем, если нет билетов
        }

        $message = "⏰ <b>Напоминание</b>\n\n";
        $message .= "У вас есть {$user->tickets_available} билет(ов) для прокрута рулетки.\n";
        $message .= "Не упустите шанс выиграть призы! 🎰";

        return self::sendNotification($user->telegram_id, $message);
    }
}

