<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\StarPayment;
use App\Models\PaymentError;
use App\Models\UserTicket;
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

            $amount = 50; // Фиксированная сумма: 50 звезд
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
                
                // Используем createStarsInvoice для правильного формата Stars
                // Для Stars: amount передается напрямую в единицах звёзд (50, а не 50000)
                $invoiceResult = $bot->createStarsInvoice(
                    userId: (int) $telegramId, // Используется для логирования, но не передается в API
                    title: 'Покупка билетов',
                    description: 'Вы обмениваете 50 звёзд и получаете 20 прокрутов рулетки',
                    payload: json_encode([
                        'payment_id' => $payment->id,
                        'purpose' => 'buy_spin_bundle',
                        'stars_amount' => $amount,
                        'tickets_amount' => $ticketsAmount,
                    ]),
                    amount: $amount, // 50 звёзд (передается напрямую, без умножения)
                    params: []
                );

                if (!isset($invoiceResult['ok']) || !$invoiceResult['ok']) {
                    throw new \Exception('Failed to create invoice: ' . ($invoiceResult['description'] ?? 'Unknown error'));
                }

                $invoiceUrl = $invoiceResult['result'] ?? null;

                if (!$invoiceUrl) {
                    throw new \Exception('Invoice URL not received from Telegram');
                }

                // Извлекаем invoice_id из URL для использования в Telegram.WebApp.openInvoice()
                // URL имеет формат: https://t.me/invoice/{invoice_id}
                $invoiceId = null;
                if (preg_match('/\/invoice\/([^\/\?]+)/', $invoiceUrl, $matches)) {
                    $invoiceId = $matches[1];
                }

                // Обновляем запись платежа с URL и ID инвойса
                $payment->invoice_url = $invoiceUrl;
                $payment->telegram_response = array_merge($invoiceResult, ['invoice_id' => $invoiceId]);
                $payment->save();

                Log::info('Stars payment invoice created successfully', [
                    'user_id' => $user->id,
                    'telegram_id' => $telegramId,
                    'payment_id' => $payment->id,
                    'invoice_url' => $invoiceUrl,
                    'invoice_id' => $invoiceId,
                    'amount' => $amount,
                    'purpose' => $purpose,
                    'tickets_amount' => $ticketsAmount,
                ]);

                return response()->json([
                    'success' => true,
                    'invoice_url' => $invoiceUrl,
                    'invoice_id' => $invoiceId, // ID для использования в Telegram.WebApp.openInvoice()
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

                PaymentError::logError(
                    'invoice_creation_error',
                    $errorMsg,
                    $user->id,
                    $request->all(),
                    null,
                    ['error' => $invoiceError->getMessage()],
                    (string) $payment->id
                );

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

            Log::info('Stars payment webhook received', [
                'query_id' => $queryId,
                'payload' => $payload,
                'charge' => $charge,
                'user' => $userData,
            ]);

            // Парсим payload для получения payment_id
            $payloadData = json_decode($payload, true);
            $paymentId = $payloadData['payment_id'] ?? null;

            if (!$paymentId) {
                Log::warning('Payment ID not found in webhook payload', [
                    'payload' => $payload,
                ]);
                return response()->json([
                    'error' => 'Payment ID not found'
                ], 400);
            }

            // Находим запись о платеже
            $payment = StarPayment::find($paymentId);

            if (!$payment) {
                Log::warning('Payment not found', ['payment_id' => $paymentId]);
                return response()->json([
                    'error' => 'Payment not found'
                ], 404);
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

            // Проверяем purpose (может быть exchange_for_spins или buy_spin_bundle)
            $validPurposes = ['exchange_for_spins', 'buy_spin_bundle'];
            $purpose = $payloadData['purpose'] ?? null;
            if (!in_array($purpose, $validPurposes)) {
                $isValid = false;
                $validationErrors[] = 'Invalid purpose: ' . ($purpose ?? 'null');
            }

            // Проверяем amount
            if (($payloadData['stars_amount'] ?? 0) !== 50) {
                $isValid = false;
                $validationErrors[] = 'Invalid amount';
            }

            // Проверяем charge (данные о списании)
            if (!$charge || !isset($charge['status']) || $charge['status'] !== 'paid') {
                $isValid = false;
                $validationErrors[] = 'Charge not paid';
            }

            if (!$isValid) {
                $errorMsg = 'Payment validation failed: ' . implode(', ', $validationErrors);
                Log::error($errorMsg, [
                    'payment_id' => $paymentId,
                    'payload' => $payloadData,
                    'charge' => $charge,
                ]);

                $payment->status = 'failed';
                $payment->telegram_response = [
                    'query_id' => $queryId,
                    'payload' => $payloadData,
                    'charge' => $charge,
                    'validation_errors' => $validationErrors,
                ];
                $payment->save();

                PaymentError::logError(
                    'payment_validation_error',
                    $errorMsg,
                    $payment->user_id,
                    $request->all(),
                    400,
                    ['validation_errors' => $validationErrors],
                    (string) $paymentId
                );

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
                    'tickets_before' => $ticketsBefore,
                    'tickets_after' => $user->tickets_available,
                    'tickets_added' => $ticketsToAdd,
                ]);

                DB::commit();

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

                PaymentError::logError(
                    'payment_processing_error',
                    $errorMsg,
                    $payment->user_id,
                    $request->all(),
                    500,
                    ['error' => $e->getMessage()],
                    (string) $paymentId
                );

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
                    'required_amount' => 50,
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
                    'required_amount' => 50,
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
}
