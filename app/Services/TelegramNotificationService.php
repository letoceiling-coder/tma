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
            $payload = [
                'chat_id' => $telegramId,
                'text' => $message,
                'parse_mode' => $options['parse_mode'] ?? 'HTML',
                'disable_web_page_preview' => $options['disable_web_page_preview'] ?? true,
            ];

            // Добавляем reply_markup, если передан
            if (isset($options['reply_markup'])) {
                $payload['reply_markup'] = $options['reply_markup'];
            }

            $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", $payload);

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

        $message = '';
        $keyboard = null;
        
        // Правильные шаблоны сообщений для каждого типа приза
        if ($prizeType === 'money' && $prizeValue > 0) {
            $message = "Поздравляем! Вы выиграли {$prizeValue} рублей!";
        } elseif ($prizeType === 'ticket' && $prizeValue > 0) {
            // Правильное склонение для билетов
            if ($prizeValue === 1) {
                $message = "Поздравляем! Вы выиграли 1 дополнительный билет!";
            } else {
                $message = "Поздравляем! Вы выиграли {$prizeValue} дополнительных билетов!";
            }
        } elseif ($prizeType === 'secret_box') {
            $message = "Поздравляем! Вы выиграли подарок от спонсора. Свяжитесь с администратором.";
        } else {
            // Пустой сектор или некорректный тип - не отправляем сообщение
            return false;
        }
        
        // Добавляем кнопку "Связаться" только для денег и секретного бокса (не для билетов)
        if ($adminLink && ($prizeType === 'money' || $prizeType === 'secret_box')) {
            $keyboard = \App\Telegram\Keyboard::inline()
                ->url('Связаться', $adminLink)
                ->get();
        }

        $options = [];
        if ($keyboard) {
            $options['reply_markup'] = json_encode($keyboard);
        }

        return self::sendNotification($user->telegram_id, $message, $options);
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
     * Уведомление о доступности бесплатной прокрутки (24 часа после последней прокрутки)
     * 
     * @param User $user
     * @return bool
     */
    public static function notifyFreeSpinAvailable(User $user): bool
    {
        if (!$user->telegram_id) {
            return false;
        }

        // Проверяем, что у пользователя есть билеты
        if ($user->tickets_available <= 0) {
            return false;
        }

        $message = "У тебя снова есть возможность бесплатно прокрутить рулетку🧡";

        // Получаем URL Mini App
        $miniAppUrl = config('telegram.mini_app_url');
        
        if (empty($miniAppUrl)) {
            $miniAppUrl = config('app.url');
        }

        // Создаем клавиатуру с кнопкой Mini App
        $keyboard = \App\Telegram\Keyboard::inline()
            ->webApp('🎰 Крутить рулетку', $miniAppUrl)
            ->get();

        return self::sendNotification($user->telegram_id, $message, [
            'reply_markup' => json_encode($keyboard),
        ]);
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

