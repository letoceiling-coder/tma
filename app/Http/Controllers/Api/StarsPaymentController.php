<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\StarPayment;
use App\Models\PaymentError;
use Illuminate\Support\Facades\Schema;
use App\Models\UserTicket;
use App\Models\WheelSetting;
use App\Services\TelegramService;
use App\Telegram\Bot;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class StarsPaymentController extends Controller
{
    /**
     * Создать инвойс для покупки 20 прокрутов за 50 звезд
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function createInvoice(Request $request): JsonResponse
    {
        try {
            $initData = $request->header('X-Telegram-Init-Data') ?? $request->query('initData');
            
            if (!$initData) {
                return response()->json([
                    'success' => false,
                    'error' => 'Init data not provided',
                    'message' => 'Ошибка авторизации'
                ], 401);
            }

            $telegramId = TelegramService::getTelegramId($initData);
            
            if (!$telegramId) {
                return response()->json([
                    'success' => false,
                    'error' => 'User ID not found',
                    'message' => 'Ошибка авторизации'
                ], 401);
            }

            $user = User::where('telegram_id', $telegramId)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'User not found',
                    'message' => 'Пользователь не найден'
                ], 404);
            }

            // Получаем настройки из админки
            $settings = WheelSetting::getSettings();
            $amount = $settings->getValidStarsPerTicketPurchase(); // Количество звёзд (по умолчанию 50)
            $ticketsAmount = 20; // Фиксированное количество билетов
            $purpose = 'buy_spin_bundle';

            // Проверка баланса звёзд через Telegram API перед созданием инвойса
            try {
                $bot = new Bot();
                
                // Получаем транзакции пользователя для проверки баланса
                // Примечание: Telegram Bot API не предоставляет прямой метод getBalance
                // Но мы можем проверить через getStarTransactions или полагаться на проверку при открытии инвойса
                // Для надежности проверяем на клиенте, а здесь создаем инвойс
                // Telegram сам проверит баланс при открытии инвойса
                
            } catch (\Exception $balanceCheckError) {
                Log::warning('Balance check failed, continuing with invoice creation', [
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                    'error' => $balanceCheckError->getMessage(),
                ]);
                // Продолжаем создание инвойса - Telegram проверит баланс при открытии
            }
            
            // Создаем запись о платеже со статусом pending
            $payment = StarPayment::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'purpose' => $purpose,
                'status' => 'pending',
                'payload' => [
                    'purpose' => $purpose,
                    'stars_amount' => $amount,
                    'tickets_amount' => $ticketsAmount,
                ],
            ]);

            // Создаем инвойс через Telegram Bot API для Stars
            try {
                $bot = new Bot();
                
                // Логируем начало создания инвойса
                Log::channel('stars-payments')->info('Creating Stars invoice', [
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                    'payment_id' => $payment->id,
                    'amount' => $amount,
                    'tickets_amount' => $ticketsAmount,
                ]);
                
                // Используем createStarsInvoice для правильного формата Stars
                // Для Stars: amount передается напрямую в единицах звёзд (из админ-панели)
                // amount берется из настройки stars_per_ticket_purchase (по умолчанию 50)
                $invoiceResult = $bot->createStarsInvoice(
                    userId: (int) $telegramId, // Используется для логирования, но не передается в API
                    title: 'Покупка билетов',
                    description: "Обмен ⭐️ на прокрутки",
                    payload: json_encode([
                        'payment_id' => $payment->id,
                        'purpose' => 'buy_spin_bundle',
                        'stars_amount' => $amount, // Количество звёзд из админ-панели
                        'tickets_amount' => $ticketsAmount,
                    ]),
                    amount: $amount, // Количество звёзд из настроек админ-панели (stars_per_ticket_purchase)
                    params: []
                );
                
                // Логируем результат создания инвойса
                Log::channel('stars-payments')->info('Stars invoice created', [
                    'user_id' => $user->id,
                    'payment_id' => $payment->id,
                    'invoice_result_ok' => $invoiceResult['ok'] ?? false,
                    'has_invoice_url' => isset($invoiceResult['result']),
                ]);

                if (!isset($invoiceResult['ok']) || !$invoiceResult['ok']) {
                    $errorDescription = $invoiceResult['description'] ?? 'Unknown error';
                    $errorCode = $invoiceResult['error_code'] ?? null;
                    
                    // Логируем в отдельный файл для Stars платежей
                    Log::channel('stars-payments')->error('Telegram API failed to create invoice', [
                        'user_id' => $user->id,
                        'telegram_id' => $telegramId,
                        'payment_id' => $payment->id,
                        'error_code' => $errorCode,
                        'error_description' => $errorDescription,
                        'invoice_result' => $invoiceResult,
                        'amount' => $amount,
                        'tickets_amount' => $ticketsAmount,
                        'currency' => 'XTR',
                        'provider_token_empty' => true,
                    ]);
                    
                    // Также логируем в основной лог
                    Log::error('Telegram API failed to create invoice', [
                        'user_id' => $user->id,
                        'telegram_id' => $telegramId,
                        'payment_id' => $payment->id,
                        'error_code' => $errorCode,
                        'error_description' => $errorDescription,
                    ]);
                    
                    // Обновляем статус платежа на failed
                    $payment->status = 'failed';
                    $payment->telegram_response = [
                        'error' => $errorDescription,
                        'error_code' => $errorCode,
                    ];
                    $payment->save();
                    
                    return response()->json([
                        'success' => false,
                        'error' => 'invoice_creation_failed',
                        'message' => 'Ошибка при создании платежа. Попробуйте снова позже.',
                        'error_code' => $errorCode,
                        'error_description' => $errorDescription,
                    ], 500);
                }

                $invoiceUrl = $invoiceResult['result'] ?? null;

                if (!$invoiceUrl) {
                    Log::error('Invoice URL is null in response', [
                        'invoice_result' => $invoiceResult,
                        'user_id' => $user->id,
                        'payment_id' => $payment->id,
                    ]);
                    throw new \Exception('Invoice URL not received from Telegram');
                }

                // Проверяем тип результата
                if (!is_string($invoiceUrl)) {
                    Log::error('Invoice URL is not a string', [
                        'invoice_url_type' => gettype($invoiceUrl),
                        'invoice_url_value' => $invoiceUrl,
                        'invoice_result' => $invoiceResult,
                    ]);
                    throw new \Exception('Invalid invoice URL format received from Telegram');
                }

                // ВАЖНО: Telegram.WebApp.openInvoice() требует полный HTTPS URL, а не slug!
                // URL должен начинаться с https://t.me/ и быть валидным HTTPS URL
                // Не нужно извлекать slug - передаем URL как есть
                
                // Валидация URL
                if (!preg_match('/^https:\/\/t\.me\//', $invoiceUrl)) {
                    Log::error('Invalid invoice URL format - must start with https://t.me/', [
                        'invoice_url' => $invoiceUrl,
                        'user_id' => $user->id,
                        'payment_id' => $payment->id,
                    ]);
                    throw new \Exception('Invalid invoice URL format: must be a valid Telegram HTTPS URL');
                }

                // Убираем пробелы и лишние символы в конце URL
                $invoiceUrl = trim($invoiceUrl);
                
                // Обновляем запись платежа с полным URL
                $payment->invoice_url = $invoiceUrl;
                $payment->telegram_response = array_merge($invoiceResult, [
                    'invoice_url_validated' => true,
                ]);
                $payment->save();

                // Логируем успешное создание инвойса в отдельный файл
                Log::channel('stars-payments')->info('Stars payment invoice created successfully', [
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                    'payment_id' => $payment->id,
                    'invoice_url' => $invoiceUrl,
                    'amount' => $amount,
                    'purpose' => $purpose,
                    'tickets_amount' => $ticketsAmount,
                    'invoice_url_length' => strlen($invoiceUrl),
                    'invoice_url_starts_with' => substr($invoiceUrl, 0, 20),
                ]);
                
                // Также логируем в основной лог
                Log::info('Stars payment invoice created successfully', [
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                    'payment_id' => $payment->id,
                    'invoice_url' => $invoiceUrl,
                    'amount' => $amount,
                    'tickets_amount' => $ticketsAmount,
                ]);

                // Финальная проверка перед возвратом
                if (empty($invoiceUrl) || !is_string($invoiceUrl)) {
                    Log::error('Invoice URL is invalid before returning to client', [
                        'user_id' => $user->id,
                        'payment_id' => $payment->id,
                        'invoice_url' => $invoiceUrl,
                    ]);
                    
                    $payment->status = 'failed';
                    $payment->save();
                    
                    return response()->json([
                        'success' => false,
                        'error' => 'invalid_invoice_url',
                        'message' => 'Ошибка при создании платежа. Попробуйте снова позже.',
                    ], 500);
                }

                return response()->json([
                    'success' => true,
                    'invoice_url' => $invoiceUrl, // Полный HTTPS URL для Telegram.WebApp.openInvoice()
                    'payment_id' => $payment->id,
                    'amount' => $amount,
                    'tickets_amount' => $ticketsAmount,
                ]);

            } catch (\Exception $invoiceError) {
                // Логируем ошибку создания инвойса
                $errorMsg = 'Failed to create invoice: ' . $invoiceError->getMessage();
                Log::error($errorMsg, [
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                    'payment_id' => $payment->id,
                    'error' => $invoiceError->getMessage(),
                    'trace' => $invoiceError->getTraceAsString(),
                ]);

                $payment->status = 'failed';
                $payment->save();

                // Логируем в таблицу ошибок (если таблица существует)
                try {
                    if (class_exists(PaymentError::class) && Schema::hasTable('payment_errors')) {
                        PaymentError::logError(
                            'invoice_creation_error',
                            $errorMsg,
                            $user->id,
                            $request->all(),
                            null,
                            ['error' => $invoiceError->getMessage()],
                            (string) $payment->id
                        );
                    }
                } catch (\Exception $logError) {
                    Log::warning('Failed to log error to payment_errors table', [
                        'error' => $logError->getMessage(),
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'error' => 'Failed to create invoice',
                    'message' => 'Ошибка при создании платежа. Попробуйте ещё раз.'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error in createInvoice', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => 'Произошла ошибка при обработке запроса'
            ], 500);
        }
    }

    /**
     * Webhook для обработки успешной оплаты от Telegram
     * Вызывается после успешной оплаты через Telegram Stars
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            // Telegram отправляет данные в формате:
            // {
            //   "query_id": "...",
            //   "user": {...},
            //   "payload": "...",
            //   "charge": {...}
            // }
            
            $queryId = $request->input('query_id');
            $payload = $request->input('payload');
            $charge = $request->input('charge');
            $userData = $request->input('user');

            // Логируем получение webhook в отдельный файл для Stars платежей
            Log::channel('stars-payments')->info('Stars payment webhook received', [
                'query_id' => $queryId,
                'has_payload' => !empty($payload),
                'has_charge' => !empty($charge),
                'has_user' => !empty($userData),
                'charge_status' => $charge['status'] ?? null,
            ]);
            
            // Также логируем в основной лог
            Log::info('Stars payment webhook received', [
                'query_id' => $queryId,
                'charge_status' => $charge['status'] ?? null,
            ]);

            // Парсим payload для получения payment_id
            $payloadData = json_decode($payload, true);
            
            if (!is_array($payloadData)) {
                Log::error('Invalid payload format in webhook', [
                    'payload' => $payload,
                ]);
                return response()->json([
                    'error' => 'Invalid payload format'
                ], 400);
            }
            
            $paymentId = $payloadData['payment_id'] ?? null;

            if (!$paymentId) {
                Log::warning('Payment ID not found in webhook payload', [
                    'payload' => $payload,
                    'payload_data' => $payloadData,
                ]);
                return response()->json([
                    'error' => 'Payment ID not found'
                ], 400);
            }

            // Находим запись о платеже
            $payment = StarPayment::find($paymentId);

            if (!$payment) {
                Log::warning('Payment not found', [
                    'payment_id' => $paymentId,
                    'payload' => $payloadData,
                ]);
                return response()->json([
                    'error' => 'Payment not found'
                ], 404);
            }

            // ВАЖНО: Проверяем, что telegram_id из webhook совпадает с user_id из платежа
            $webhookTelegramId = $userData['id'] ?? null;
            $paymentUser = User::find($payment->user_id);
            
            if ($paymentUser && $webhookTelegramId && (int) $webhookTelegramId !== (int) $paymentUser->telegram_id) {
                Log::error('Telegram user ID mismatch in webhook', [
                    'payment_id' => $paymentId,
                    'payment_user_id' => $payment->user_id,
                    'payment_telegram_id' => $paymentUser->telegram_id,
                    'webhook_telegram_id' => $webhookTelegramId,
                ]);
                
                $payment->status = 'failed';
                $payment->telegram_response = [
                    'query_id' => $queryId,
                    'payload' => $payloadData,
                    'charge' => $charge,
                    'user' => $userData,
                    'security_error' => 'Telegram user ID mismatch',
                ];
                $payment->save();
                
                // Логируем в таблицу ошибок (если таблица существует)
                try {
                    if (class_exists(PaymentError::class) && Schema::hasTable('payment_errors')) {
                        PaymentError::logError(
                            'security_error',
                            'Telegram user ID mismatch in webhook',
                            $payment->user_id,
                            $request->all(),
                            403,
                            [
                                'payment_telegram_id' => $paymentUser->telegram_id,
                                'webhook_telegram_id' => $webhookTelegramId,
                            ],
                            (string) $paymentId
                        );
                    }
                } catch (\Exception $logError) {
                    Log::warning('Failed to log error to payment_errors table', [
                        'error' => $logError->getMessage(),
                    ]);
                }
                
                return response()->json([
                    'error' => 'Security validation failed'
                ], 403);
            }

            // Проверяем, что транзакция еще не обработана
            if ($payment->status === 'success') {
                Log::warning('Payment already processed', ['payment_id' => $paymentId]);
                return response()->json([
                    'error' => 'Payment already processed'
                ], 400);
            }

            // Валидация транзакции
            $isValid = true;
            $validationErrors = [];

            // Проверяем purpose (может быть exchange_for_spins, buy_spin_bundle или buy_tickets)
            $validPurposes = ['exchange_for_spins', 'buy_spin_bundle', 'buy_tickets'];
            $purpose = $payloadData['purpose'] ?? null;
            if (!in_array($purpose, $validPurposes)) {
                $isValid = false;
                $validationErrors[] = 'Invalid purpose: ' . ($purpose ?? 'null');
            }

            // Проверяем amount - получаем настройку из базы
            $settings = WheelSetting::getSettings();
            $expectedAmount = $settings->getValidStarsPerTicketPurchase();
            $receivedAmount = $payloadData['stars_amount'] ?? 0;
            
            if ($receivedAmount !== $expectedAmount) {
                $isValid = false;
                $validationErrors[] = "Invalid amount: expected {$expectedAmount}, got {$receivedAmount}";
            }

            // Проверяем charge (данные о списании)
            if (!$charge || !isset($charge['status']) || $charge['status'] !== 'paid') {
                $isValid = false;
                $validationErrors[] = 'Charge not paid';
            }

            if (!$isValid) {
                $errorMsg = 'Payment validation failed: ' . implode(', ', $validationErrors);
                
                // Логируем ошибку валидации в отдельный файл
                Log::channel('stars-payments')->error('Stars payment validation failed', [
                    'payment_id' => $paymentId,
                    'validation_errors' => $validationErrors,
                    'payload' => $payloadData,
                    'charge_status' => $charge['status'] ?? null,
                ]);
                
                // Также логируем в основной лог
                Log::error($errorMsg, [
                    'payment_id' => $paymentId,
                    'validation_errors' => $validationErrors,
                ]);

                $payment->status = 'failed';
                $payment->telegram_response = [
                    'query_id' => $queryId,
                    'payload' => $payloadData,
                    'charge' => $charge,
                    'validation_errors' => $validationErrors,
                ];
                $payment->save();

                // Логируем в таблицу ошибок (если таблица существует)
                try {
                    if (class_exists(PaymentError::class) && Schema::hasTable('payment_errors')) {
                        PaymentError::logError(
                            'payment_validation_error',
                            $errorMsg,
                            $payment->user_id,
                            $request->all(),
                            400,
                            ['validation_errors' => $validationErrors],
                            (string) $paymentId
                        );
                    }
                } catch (\Exception $logError) {
                    Log::warning('Failed to log error to payment_errors table', [
                        'error' => $logError->getMessage(),
                    ]);
                }

                return response()->json([
                    'error' => 'Payment validation failed'
                ], 400);
            }

            // Транзакция валидна - начисляем билеты
            DB::beginTransaction();

            try {
                // ВАЖНО: Блокируем строку платежа для защиты от двойного списания
                $payment = StarPayment::where('id', $paymentId)->lockForUpdate()->first();
                
                if (!$payment) {
                    DB::rollBack();
                    return response()->json([
                        'error' => 'Payment not found'
                    ], 404);
                }

                // Повторная проверка статуса после блокировки
                if ($payment->status === 'success') {
                    DB::rollBack();
                    Log::warning('Payment already processed (after lock)', ['payment_id' => $paymentId]);
                    return response()->json([
                        'error' => 'Payment already processed'
                    ], 400);
                }

                // Обновляем статус платежа
                $payment->status = 'success';
                $payment->payment_id = $charge['telegram_payment_charge_id'] ?? $queryId;
                $payment->paid_at = now();
                $payment->telegram_response = [
                    'query_id' => $queryId,
                    'payload' => $payloadData,
                    'charge' => $charge,
                    'user' => $userData,
                ];
                $payment->save();

                // Начисляем билеты пользователю
                // ВАЖНО: Блокируем строку пользователя для защиты от race condition
                $user = User::where('id', $payment->user_id)->lockForUpdate()->first();
                
                if (!$user) {
                    DB::rollBack();
                    Log::error('User not found after lock', ['user_id' => $payment->user_id]);
                    return response()->json([
                        'error' => 'User not found'
                    ], 404);
                }

                $ticketsBefore = $user->tickets_available;
                $ticketsToAdd = 20; // Фиксированное количество

                $user->tickets_available = $user->tickets_available + $ticketsToAdd;
                
                // Если билеты стали больше 0, сбрасываем точку восстановления
                if ($user->tickets_available > 0) {
                    $user->tickets_depleted_at = null;
                    $user->referral_popup_shown_at = null;
                }
                
                $user->save();

                // Создаем запись в истории билетов
                UserTicket::create([
                    'user_id' => $user->id,
                    'tickets_count' => $ticketsToAdd,
                    'restored_at' => null,
                    'source' => 'stars_payment',
                ]);

                Log::info('Stars payment processed successfully', [
                    'payment_id' => $paymentId,
                    'user_id' => $user->id,
                    'telegram_id' => $user->telegram_id,
                    'stars_amount' => $payment->amount,
                    'tickets_before' => $ticketsBefore,
                    'tickets_after' => $user->tickets_available,
                    'tickets_added' => $ticketsToAdd,
                    'purpose' => $payloadData['purpose'] ?? null,
                    'query_id' => $queryId,
                ]);

                DB::commit();

                // Логируем успешную обработку платежа в отдельный файл
                Log::channel('stars-payments')->info('Stars payment processed successfully', [
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                    'payment_id' => $paymentId,
                    'query_id' => $queryId,
                    'stars_amount' => $payloadData['stars_amount'] ?? null,
                    'tickets_amount' => $payloadData['tickets_amount'] ?? null,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment processed successfully'
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                
                $errorMsg = 'Error processing payment: ' . $e->getMessage();
                Log::error($errorMsg, [
                    'payment_id' => $paymentId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $payment->status = 'failed';
                $payment->save();

                // Логируем в таблицу ошибок (если таблица существует)
                try {
                    if (class_exists(PaymentError::class) && Schema::hasTable('payment_errors')) {
                        PaymentError::logError(
                            'payment_processing_error',
                            $errorMsg,
                            $payment->user_id,
                            $request->all(),
                            500,
                            ['error' => $e->getMessage()],
                            (string) $paymentId
                        );
                    }
                } catch (\Exception $logError) {
                    Log::warning('Failed to log error to payment_errors table', [
                        'error' => $logError->getMessage(),
                    ]);
                }

                return response()->json([
                    'error' => 'Error processing payment'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error in payment webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Получить баланс звезд пользователя
     * Использует Telegram Bot API getStarTransactions для получения баланса
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getStarsBalance(Request $request): JsonResponse
    {
        try {
            $initData = $request->header('X-Telegram-Init-Data') ?? $request->query('initData');
            
            if (!$initData) {
                return response()->json([
                    'success' => false,
                    'error' => 'Init data not provided',
                    'message' => 'Ошибка авторизации'
                ], 401);
            }

            $telegramId = TelegramService::getTelegramId($initData);
            
            if (!$telegramId) {
                return response()->json([
                    'success' => false,
                    'error' => 'User ID not found',
                    'message' => 'Ошибка авторизации'
                ], 401);
            }

            $user = User::where('telegram_id', $telegramId)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'User not found',
                    'message' => 'Пользователь не найден'
                ], 404);
            }

            // Получаем баланс через Telegram Bot API
            try {
                $bot = new Bot();
                
                // Получаем транзакции пользователя для расчета баланса
                // Примечание: Telegram Bot API не предоставляет прямой метод getBalance
                // Но можно использовать getStarTransactions для получения последних транзакций
                // и примерного расчета баланса
                
                $transactions = $bot->getStarTransactions([
                    'user_id' => (int) $telegramId,
                    'offset' => 0,
                    'limit' => 100, // Получаем последние 100 транзакций
                ]);
                
                // Рассчитываем примерный баланс на основе транзакций
                // Это приблизительная оценка, точный баланс лучше проверять на клиенте
                $estimatedBalance = null;
                if (isset($transactions['ok']) && $transactions['ok'] && isset($transactions['result']['transactions'])) {
                    // Логика расчета баланса на основе транзакций
                    // Пока возвращаем null, так как точный баланс лучше проверять на клиенте
                }
                
                return response()->json([
                    'success' => true,
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                    'balance' => $estimatedBalance, // null если не удалось рассчитать
                    'required_amount' => WheelSetting::getSettings()->getValidStarsPerTicketPurchase(),
                    'note' => 'Balance should be checked on client side using Telegram.WebApp.cloudStorage.get("stars_balance") or Telegram.WebApp.getUser()',
                ]);

            } catch (\Exception $apiError) {
                Log::error('Error getting stars balance from Telegram API', [
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                    'error' => $apiError->getMessage(),
                ]);

                // Возвращаем успешный ответ, но с предупреждением
                // Баланс будет проверен при открытии инвойса
                return response()->json([
                    'success' => true,
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                    'note' => 'Balance check failed, will be verified when opening invoice',
                    'required_amount' => WheelSetting::getSettings()->getValidStarsPerTicketPurchase(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error getting stars balance', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => 'Произошла ошибка при проверке баланса'
            ], 500);
        }
    }

    /**
     * Логирование ошибок при открытии инвойса на клиенте
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function logError(Request $request): JsonResponse
    {
        try {
            $initData = $request->header('X-Telegram-Init-Data') ?? $request->query('initData');
            
            if (!$initData) {
                return response()->json([
                    'success' => false,
                    'error' => 'Init data not provided',
                ], 401);
            }

            $telegramId = TelegramService::getTelegramId($initData);
            
            if (!$telegramId) {
                return response()->json([
                    'success' => false,
                    'error' => 'User ID not found',
                ], 401);
            }

            $user = User::where('telegram_id', $telegramId)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'User not found',
                ], 404);
            }

            $paymentId = $request->input('payment_id');
            $errorType = $request->input('error_type', 'unknown_error');
            $errorMessage = $request->input('error_message', 'Unknown error');
            $invoiceSlug = $request->input('invoice_slug');
            $stack = $request->input('stack');

            // Логируем ошибку в отдельный файл для Stars платежей
            Log::channel('stars-payments')->error('Stars payment invoice open error', [
                'user_id' => $user->id,
                'telegram_id' => $telegramId,
                'payment_id' => $paymentId,
                'error_type' => $errorType,
                'error_message' => $errorMessage,
                'invoice_slug' => $invoiceSlug,
                'stack' => $stack,
                'request_data' => $request->all(),
            ]);
            
            // Также логируем в основной лог
            Log::error('Stars payment invoice open error', [
                'user_id' => $user->id,
                'telegram_id' => $telegramId,
                'payment_id' => $paymentId,
                'error_type' => $errorType,
                'error_message' => $errorMessage,
            ]);

            // Если есть payment_id, обновляем статус платежа
            if ($paymentId) {
                $payment = StarPayment::find($paymentId);
                if ($payment) {
                    $payment->status = 'failed';
                    $payment->telegram_response = array_merge(
                        $payment->telegram_response ?? [],
                        [
                            'client_error' => [
                                'type' => $errorType,
                                'message' => $errorMessage,
                                'invoice_slug' => $invoiceSlug,
                                'timestamp' => now()->toIso8601String(),
                            ],
                        ]
                    );
                    $payment->save();
                }
            }

            // Логируем в таблицу ошибок (если таблица существует)
            try {
                if (class_exists(PaymentError::class) && \Schema::hasTable('payment_errors')) {
                    PaymentError::logError(
                        $errorType,
                        $errorMessage,
                        $user->id,
                        $request->all(),
                        500,
                        [
                            'invoice_slug' => $invoiceSlug,
                            'stack' => $stack,
                        ],
                        (string) $paymentId
                    );
                }
            } catch (\Exception $logError) {
                // Игнорируем ошибки логирования в БД, основное логирование уже выполнено через Log::error
                Log::warning('Failed to log error to payment_errors table', [
                    'error' => $logError->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Error logged successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Error logging invoice open error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to log error',
            ], 500);
        }
    }
}
