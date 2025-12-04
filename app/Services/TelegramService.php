<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class TelegramService
{
    /**
     * Валидация подписи initData от Telegram WebApp
     * 
     * @param string $initData
     * @param string $botToken
     * @return bool
     */
    public static function validateInitData(string $initData, string $botToken): bool
    {
        try {
            // Парсим initData
            $data = [];
            parse_str($initData, $data);
            
            if (!isset($data['hash'])) {
                return false;
            }
            
            $hash = $data['hash'];
            unset($data['hash']);
            
            // Сортируем параметры
            ksort($data);
            
            // Формируем data-check-string
            $dataCheckString = [];
            foreach ($data as $key => $value) {
                $dataCheckString[] = "{$key}={$value}";
            }
            $dataCheckString = implode("\n", $dataCheckString);
            
            // Создаем секретный ключ
            $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
            
            // Вычисляем хеш
            $calculatedHash = bin2hex(hash_hmac('sha256', $dataCheckString, $secretKey, true));
            
            // Сравниваем хеши
            return hash_equals($calculatedHash, $hash);
            
        } catch (\Exception $e) {
            Log::error('Telegram initData validation error', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Парсинг initData и извлечение данных пользователя
     * 
     * @param string $initData
     * @return array|null
     */
    public static function parseInitData(string $initData): ?array
    {
        try {
            // В режиме разработки поддерживаем mock initData
            if (config('app.debug') && $initData === 'mock_init_data_for_development') {
                return [
                    'user' => [
                        'id' => 999999999,
                        'first_name' => 'Dev',
                        'last_name' => 'User',
                        'username' => 'devuser',
                        'language_code' => 'ru',
                    ],
                    'auth_date' => time(),
                ];
            }
            
            $data = [];
            parse_str($initData, $data);

            // Декодируем user данные если они есть
            if (isset($data['user'])) {
                $data['user'] = json_decode($data['user'], true);
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Telegram initData parsing error', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Получить telegram_id из initData
     * 
     * @param string $initData
     * @return int|null
     */
    public static function getTelegramId(string $initData): ?int
    {
        $data = self::parseInitData($initData);
        return $data['user']['id'] ?? null;
    }
}

