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
     * @param string|null $adminLink Ссылка на админа для связи
     * @return bool
     */
    public static function notifyWin(User $user, int $prizeValue, string $prizeType, ?string $adminLink = null): bool
    {
        if (!$user->telegram_id) {
            return false;
        }

        $message = "🎉 <b>Поздравляем с выигрышем!</b>\n\n";
        
        // Правильные шаблоны сообщений для каждого типа приза
        if ($prizeType === 'money' && $prizeValue > 0) {
            $message .= "Вы выиграли <b>{$prizeValue} ₽</b>! 💰\n\n";
            $message .= "Для получения приза свяжитесь с администратором.";
        } elseif ($prizeType === 'ticket' && $prizeValue > 0) {
            // Исправляем сообщение для билетов
            $ticketWord = $prizeValue == 1 ? 'билет' : ($prizeValue < 5 ? 'билета' : 'билетов');
            $message .= "Вы выиграли <b>{$prizeValue} {$ticketWord}</b>! 🎫\n\n";
            $message .= "Билеты уже добавлены на ваш счет. Крутите колесо и выигрывайте призы!";
        } elseif ($prizeType === 'secret_box') {
            $message .= "Вы выиграли <b>Секретный бокс</b>! 🎁\n\n";
            $message .= "Для получения приза свяжитесь с администратором.";
        } else {
            // Пустой сектор или некорректный тип - не должно вызываться, но на всякий случай
            return false;
        }
        
        // Добавляем ссылку на админа, если предоставлена
        if ($adminLink) {
            $message .= "\n\n<a href=\"{$adminLink}\">💬 Написать администратору</a>";
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

    /**
     * Уведомление о новом реферале
     * 
     * @param User $referrer Пользователь, который пригласил
     * @param User $invitedUser Новый пользователь, который зарегистрировался
     * @return bool
     */
    public static function notifyNewReferral(User $referrer, User $invitedUser): bool
    {
        if (!$referrer->telegram_id) {
            return false;
        }

        $message = "🎉 <b>Новый реферал!</b>\n\n";
        $message .= "По вашей реферальной ссылке зарегистрировался новый пользователь!\n\n";
        $message .= "🎫 <b>Вам начислен 1 билет</b> за приглашение.\n";
        $message .= "Теперь у вас <b>{$referrer->tickets_available} билет(ов)</b>.\n\n";
        $message .= "Продолжайте приглашать друзей и получайте больше билетов! 🚀";

        return self::sendNotification($referrer->telegram_id, $message);
    }
}

