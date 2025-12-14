<?php

namespace App\Telegram;

use App\Telegram\TelegramClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Класс для работы с Telegram Bot API
 * Документация: https://core.telegram.org/bots/api
 */
class Bot extends TelegramClient
{
    // ==========================================
    // Getting updates
    // ==========================================

    /**
     * Получить обновления (updates)
     */
    public function getUpdates(array $params = []): array
    {
        return $this->request('getUpdates', $params);
    }

    /**
     * Установить webhook
     */
    public function setWebhook(string $url, array $params = []): array
    {
        return $this->request('setWebhook', array_merge(['url' => $url], $params));
    }

    /**
     * Удалить webhook
     */
    public function deleteWebhook(bool $dropPendingUpdates = false): array
    {
        return $this->request('deleteWebhook', [
            'drop_pending_updates' => $dropPendingUpdates,
        ]);
    }

    /**
     * Получить информацию о webhook
     */
    public function getWebhookInfo(): array
    {
        return $this->request('getWebhookInfo');
    }

    // ==========================================
    // Sending messages
    // ==========================================

    /**
     * Отправить текстовое сообщение
     */
    public function sendMessage(
        int|string $chatId,
        string $text,
        array $params = []
    ): array {
        // Валидация
        Validator::validateChatId($chatId);
        Validator::validateMessageText($text);
        
        if (isset($params['parse_mode'])) {
            Validator::validateParseMode($params['parse_mode']);
        }
        
        return $this->request('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
        ], $params));
    }

    /**
     * Переслать сообщение
     */
    public function forwardMessage(
        int|string $chatId,
        int|string $fromChatId,
        int $messageId,
        array $params = []
    ): array {
        return $this->request('forwardMessage', array_merge([
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId,
        ], $params));
    }

    /**
     * Скопировать сообщение
     */
    public function copyMessage(
        int|string $chatId,
        int|string $fromChatId,
        int $messageId,
        array $params = []
    ): array {
        return $this->request('copyMessage', array_merge([
            'chat_id' => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId,
        ], $params));
    }

    /**
     * Отправить фото
     */
    public function sendPhoto(
        int|string $chatId,
        string $photo,
        array $params = []
    ): array {
        Validator::validateChatId($chatId);
        
        if (isset($params['caption'])) {
            Validator::validateCaption($params['caption']);
        }
        
        return $this->request('sendPhoto', array_merge([
            'chat_id' => $chatId,
            'photo' => $photo,
        ], $params));
    }

    /**
     * Отправить аудио
     */
    public function sendAudio(
        int|string $chatId,
        string $audio,
        array $params = []
    ): array {
        return $this->request('sendAudio', array_merge([
            'chat_id' => $chatId,
            'audio' => $audio,
        ], $params));
    }

    /**
     * Отправить документ
     */
    public function sendDocument(
        int|string $chatId,
        string $document,
        array $params = []
    ): array {
        return $this->request('sendDocument', array_merge([
            'chat_id' => $chatId,
            'document' => $document,
        ], $params));
    }

    /**
     * Отправить видео
     */
    public function sendVideo(
        int|string $chatId,
        string $video,
        array $params = []
    ): array {
        return $this->request('sendVideo', array_merge([
            'chat_id' => $chatId,
            'video' => $video,
        ], $params));
    }

    /**
     * Отправить анимацию (GIF)
     */
    public function sendAnimation(
        int|string $chatId,
        string $animation,
        array $params = []
    ): array {
        return $this->request('sendAnimation', array_merge([
            'chat_id' => $chatId,
            'animation' => $animation,
        ], $params));
    }

    /**
     * Отправить голосовое сообщение
     */
    public function sendVoice(
        int|string $chatId,
        string $voice,
        array $params = []
    ): array {
        return $this->request('sendVoice', array_merge([
            'chat_id' => $chatId,
            'voice' => $voice,
        ], $params));
    }

    /**
     * Отправить видео заметку
     */
    public function sendVideoNote(
        int|string $chatId,
        string $videoNote,
        array $params = []
    ): array {
        return $this->request('sendVideoNote', array_merge([
            'chat_id' => $chatId,
            'video_note' => $videoNote,
        ], $params));
    }

    /**
     * Отправить группу медиа файлов
     */
    public function sendMediaGroup(
        int|string $chatId,
        array $media,
        array $params = []
    ): array {
        Validator::validateChatId($chatId);
        Validator::validateMediaGroup($media);
        
        return $this->request('sendMediaGroup', array_merge([
            'chat_id' => $chatId,
            'media' => json_encode($media),
        ], $params));
    }

    /**
     * Отправить локацию
     */
    public function sendLocation(
        int|string $chatId,
        float $latitude,
        float $longitude,
        array $params = []
    ): array {
        Validator::validateChatId($chatId);
        Validator::validateLatitude($latitude);
        Validator::validateLongitude($longitude);
        
        return $this->request('sendLocation', array_merge([
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ], $params));
    }

    /**
     * Отправить место на карте
     */
    public function sendVenue(
        int|string $chatId,
        float $latitude,
        float $longitude,
        string $title,
        string $address,
        array $params = []
    ): array {
        return $this->request('sendVenue', array_merge([
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'title' => $title,
            'address' => $address,
        ], $params));
    }

    /**
     * Отправить контакт
     */
    public function sendContact(
        int|string $chatId,
        string $phoneNumber,
        string $firstName,
        array $params = []
    ): array {
        return $this->request('sendContact', array_merge([
            'chat_id' => $chatId,
            'phone_number' => $phoneNumber,
            'first_name' => $firstName,
        ], $params));
    }

    /**
     * Отправить опрос
     */
    public function sendPoll(
        int|string $chatId,
        string $question,
        array $options,
        array $params = []
    ): array {
        Validator::validateChatId($chatId);
        Validator::validatePollQuestion($question);
        Validator::validatePollOptions($options);
        
        return $this->request('sendPoll', array_merge([
            'chat_id' => $chatId,
            'question' => $question,
            'options' => json_encode($options),
        ], $params));
    }

    /**
     * Отправить игральный кубик
     */
    public function sendDice(
        int|string $chatId,
        array $params = []
    ): array {
        return $this->request('sendDice', array_merge([
            'chat_id' => $chatId,
        ], $params));
    }

    /**
     * Отправить действие в чате (typing, upload_photo, etc.)
     */
    public function sendChatAction(
        int|string $chatId,
        string $action
    ): array {
        return $this->request('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action,
        ]);
    }

    // ==========================================
    // Updating messages
    // ==========================================

    /**
     * Редактировать текст сообщения
     */
    public function editMessageText(
        string $text,
        array $params = []
    ): array {
        return $this->request('editMessageText', array_merge([
            'text' => $text,
        ], $params));
    }

    /**
     * Редактировать подпись к сообщению
     */
    public function editMessageCaption(array $params = []): array
    {
        return $this->request('editMessageCaption', $params);
    }

    /**
     * Редактировать медиа сообщение
     */
    public function editMessageMedia(
        array $media,
        array $params = []
    ): array {
        return $this->request('editMessageMedia', array_merge([
            'media' => json_encode($media),
        ], $params));
    }

    /**
     * Редактировать клавиатуру сообщения
     */
    public function editMessageReplyMarkup(array $params = []): array
    {
        return $this->request('editMessageReplyMarkup', $params);
    }

    /**
     * Остановить опрос
     */
    public function stopPoll(
        int|string $chatId,
        int $messageId,
        array $params = []
    ): array {
        return $this->request('stopPoll', array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ], $params));
    }

    /**
     * Удалить сообщение
     */
    public function deleteMessage(
        int|string $chatId,
        int $messageId
    ): array {
        Validator::validateChatId($chatId);
        Validator::validateMessageId($messageId);
        
        return $this->request('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    /**
     * Удалить несколько сообщений
     */
    public function deleteMessages(
        int|string $chatId,
        array $messageIds
    ): array {
        return $this->request('deleteMessages', [
            'chat_id' => $chatId,
            'message_ids' => json_encode($messageIds),
        ]);
    }

    // ==========================================
    // Stickers
    // ==========================================

    /**
     * Отправить стикер
     */
    public function sendSticker(
        int|string $chatId,
        string $sticker,
        array $params = []
    ): array {
        return $this->request('sendSticker', array_merge([
            'chat_id' => $chatId,
            'sticker' => $sticker,
        ], $params));
    }

    /**
     * Получить набор стикеров
     */
    public function getStickerSet(string $name): array
    {
        return $this->request('getStickerSet', ['name' => $name]);
    }

    /**
     * Загрузить файл стикера
     */
    public function uploadStickerFile(
        int $userId,
        string $sticker,
        string $stickerFormat
    ): array {
        return $this->request('uploadStickerFile', [
            'user_id' => $userId,
            'sticker' => $sticker,
            'sticker_format' => $stickerFormat,
        ]);
    }

    // ==========================================
    // Inline mode
    // ==========================================

    /**
     * Ответить на inline запрос
     */
    public function answerInlineQuery(
        string $inlineQueryId,
        array $results,
        array $params = []
    ): array {
        return $this->request('answerInlineQuery', array_merge([
            'inline_query_id' => $inlineQueryId,
            'results' => json_encode($results),
        ], $params));
    }

    /**
     * Ответить на Web App запрос
     */
    public function answerWebAppQuery(
        string $webAppQueryId,
        array $result
    ): array {
        return $this->request('answerWebAppQuery', [
            'web_app_query_id' => $webAppQueryId,
            'result' => json_encode($result),
        ]);
    }

    // ==========================================
    // Payments
    // ==========================================

    /**
     * Отправить инвойс
     */
    public function sendInvoice(
        int|string $chatId,
        string $title,
        string $description,
        string $payload,
        string $providerToken,
        string $currency,
        array $prices,
        array $params = []
    ): array {
        return $this->request('sendInvoice', array_merge([
            'chat_id' => $chatId,
            'title' => $title,
            'description' => $description,
            'payload' => $payload,
            'provider_token' => $providerToken,
            'currency' => $currency,
            'prices' => json_encode($prices),
        ], $params));
    }

    /**
     * Создать ссылку на инвойс
     * 
     * Примечание: createInvoiceLink возвращает строку (URL) в поле result, а не массив
     * Поэтому переопределяем метод, чтобы правильно обработать ответ
     * 
     * @return array ['ok' => bool, 'result' => string (invoice URL)]
     */
    public function createInvoiceLink(
        string $title,
        string $description,
        string $payload,
        string $providerToken,
        string $currency,
        array $prices,
        array $params = []
    ): array {
        // Используем protected свойство baseUrl и token из родительского класса TelegramClient
        $url = $this->baseUrl . $this->token . '/createInvoiceLink';
        
        try {
            // Для Telegram Stars (XTR) provider_token должен быть пустой строкой ""
            // Для других валют передается реальный provider_token
            $requestData = [
                'title' => $title,
                'description' => $description,
                'payload' => $payload,
                'provider_token' => $providerToken, // Для XTR это будет пустая строка ""
                'currency' => $currency,
                'prices' => json_encode($prices),
            ];
            
            // Добавляем дополнительные параметры
            $requestData = array_merge($requestData, $params);

            // Логируем запрос в отдельный файл для Stars платежей
            Log::channel('stars-payments')->debug('Creating invoice link request', [
                'currency' => $currency,
                'title' => $title,
                'description' => $description,
                'amount' => $prices[0]['amount'] ?? null,
                'provider_token_empty' => empty($providerToken),
                'prices' => $prices,
            ]);

            $response = Http::timeout(30)->post($url, $requestData);

            $result = $response->json();
            
            // Логируем ответ в отдельный файл для Stars платежей
            Log::channel('stars-payments')->debug('Invoice link response', [
                'ok' => $result['ok'] ?? false,
                'has_result' => isset($result['result']),
                'result_type' => gettype($result['result'] ?? null),
                'result_length' => isset($result['result']) ? strlen($result['result']) : null,
                'result_preview' => isset($result['result']) ? substr($result['result'], 0, 50) : null,
                'error_code' => $result['error_code'] ?? null,
                'description' => $result['description'] ?? null,
            ]);

            if (!isset($result['ok']) || !$result['ok']) {
                $errorMessage = $result['description'] ?? 'Unknown error';
                $errorCode = $result['error_code'] ?? null;
                
                // Логируем ошибку в отдельный файл для Stars платежей
                Log::channel('stars-payments')->error('Telegram API error (createInvoiceLink)', [
                    'error' => $errorMessage,
                    'error_code' => $errorCode,
                    'response' => $result,
                    'request_data' => [
                        'title' => $title,
                        'description' => $description,
                        'currency' => $currency,
                        'amount' => $prices[0]['amount'] ?? null,
                        'provider_token_empty' => empty($providerToken),
                        'prices' => $prices,
                    ],
                ]);
                
                // Также логируем в основной лог
                Log::error('Telegram API error (createInvoiceLink)', [
                    'error' => $errorMessage,
                    'error_code' => $errorCode,
                    'currency' => $currency,
                ]);
                
                // Формируем более информативное сообщение об ошибке
                $fullErrorMessage = $errorMessage;
                if ($errorCode) {
                    $fullErrorMessage .= ' (code: ' . $errorCode . ')';
                }
                
                throw new \App\Telegram\Exceptions\TelegramException($fullErrorMessage);
            }

            // createInvoiceLink возвращает строку (URL) в поле result, а не массив
            // Возвращаем полный ответ в формате ['ok' => true, 'result' => string]
            return [
                'ok' => true,
                'result' => $result['result'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('Telegram API request failed (createInvoiceLink)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \App\Telegram\Exceptions\TelegramException('Failed to execute Telegram API request: ' . $e->getMessage());
        }
    }

    /**
     * Ответить на pre-checkout запрос
     */
    public function answerPreCheckoutQuery(
        string $preCheckoutQueryId,
        bool $ok,
        ?string $errorMessage = null
    ): array {
        $params = [
            'pre_checkout_query_id' => $preCheckoutQueryId,
            'ok' => $ok,
        ];

        if ($errorMessage) {
            $params['error_message'] = $errorMessage;
        }

        return $this->request('answerPreCheckoutQuery', $params);
    }

    /**
     * Ответить на shipping запрос
     */
    public function answerShippingQuery(
        string $shippingQueryId,
        bool $ok,
        array $params = []
    ): array {
        return $this->request('answerShippingQuery', array_merge([
            'shipping_query_id' => $shippingQueryId,
            'ok' => $ok,
        ], $params));
    }

    // ==========================================
    // Telegram Stars (новая платежная система)
    // ==========================================

    /**
     * Получить транзакции Stars
     */
    public function getStarTransactions(array $params = []): array
    {
        return $this->request('getStarTransactions', $params);
    }

    /**
     * Вернуть платеж Stars
     */
    public function refundStarPayment(
        int $userId,
        string $telegramPaymentChargeId
    ): array {
        return $this->request('refundStarPayment', [
            'user_id' => $userId,
            'telegram_payment_charge_id' => $telegramPaymentChargeId,
        ]);
    }

    /**
     * Создать инвойс для Telegram Stars
     * Для Stars используется createInvoiceLink с currency="XTR" и provider_token не передается
     * 
     * @param int $userId Telegram user ID (используется для логирования, не передается в API)
     * @param string $title Название товара
     * @param string $description Описание товара
     * @param string $payload Уникальный payload для идентификации платежа
     * @param int $amount Количество звезд (передается напрямую, без умножения)
     * @param array $params Дополнительные параметры
     * @return array
     */
    public function createStarsInvoice(
        int $userId,
        string $title,
        string $description,
        string $payload,
        int $amount,
        array $params = []
    ): array {
        // ВАЖНО: Для Telegram Stars API amount передается напрямую в единицах звёзд
        // НЕ нужно умножать на 1000! Telegram Stars не имеют дробных единиц.
        // Для валюты XTR (Telegram Stars) amount - это целое число звёзд.
        
        // Для Stars инвойсов user_id не передается в createInvoiceLink
        // Пользователь определяется автоматически при открытии через Telegram.WebApp.openInvoice()
        // provider_token НЕ должен передаваться для Stars (XTR валюта)
        return $this->createInvoiceLink(
            title: $title,
            description: $description,
            payload: $payload,
            providerToken: '', // Пусто для Telegram Stars - не будет передано в запрос
            currency: 'XTR', // XTR = Telegram Stars (официальная валюта для Stars платежей)
            prices: [
                ['label' => $title, 'amount' => $amount], // Передаем amount напрямую, без умножения
            ],
            params: $params
        );
    }

    // ==========================================
    // Games
    // ==========================================

    /**
     * Отправить игру
     */
    public function sendGame(
        int $chatId,
        string $gameShortName,
        array $params = []
    ): array {
        return $this->request('sendGame', array_merge([
            'chat_id' => $chatId,
            'game_short_name' => $gameShortName,
        ], $params));
    }

    /**
     * Установить рекорд игры
     */
    public function setGameScore(
        int $userId,
        int $score,
        array $params = []
    ): array {
        return $this->request('setGameScore', array_merge([
            'user_id' => $userId,
            'score' => $score,
        ], $params));
    }

    /**
     * Получить рекорды игры
     */
    public function getGameHighScores(
        int $userId,
        array $params = []
    ): array {
        return $this->request('getGameHighScores', array_merge([
            'user_id' => $userId,
        ], $params));
    }

    // ==========================================
    // Menu Button
    // ==========================================

    /**
     * Получить информацию о боте
     */
    public function getMe(): array
    {
        return $this->request('getMe');
    }

    /**
     * Получить menu button для чата
     */
    public function getChatMenuButton(?int $chatId = null): array
    {
        $params = [];
        if ($chatId) {
            $params['chat_id'] = $chatId;
        }
        return $this->request('getChatMenuButton', $params);
    }

    /**
     * Установить menu button для чата
     * 
     * @param array|null $menuButton Структура MenuButton:
     *   - type: 'commands' | 'web_app' | 'default'
     *   - text: string (для web_app)
     *   - web_app: array { url: string } (для web_app)
     * @param int|null $chatId ID чата (null для бота по умолчанию)
     */
    public function setChatMenuButton(?array $menuButton = null, ?int $chatId = null): array
    {
        $params = [];
        if ($chatId) {
            $params['chat_id'] = $chatId;
        }
        if ($menuButton) {
            $params['menu_button'] = json_encode($menuButton);
        }
        return $this->request('setChatMenuButton', $params);
    }
}

