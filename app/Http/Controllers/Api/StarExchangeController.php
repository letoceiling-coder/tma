<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\StarExchange;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class StarExchangeController extends Controller
{
    /**
     * Инициировать обмен Telegram Stars на билеты
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function initiateExchange(Request $request): JsonResponse
    {
        try {
            $initData = $request->header('X-Telegram-Init-Data') ?? $request->query('initData');
            
            if (!$initData) {
                return response()->json([
                    'error' => 'Init data not provided'
                ], 401);
            }

            $telegramId = TelegramService::getTelegramId($initData);
            
            if (!$telegramId) {
                return response()->json([
                    'error' => 'User ID not found'
                ], 401);
            }

            $user = User::where('telegram_id', $telegramId)->first();
            
            if (!$user) {
                return response()->json([
                    'error' => 'User not found'
                ], 404);
            }

            $starsAmount = (int) $request->input('stars_amount', 50);
            $ticketsAmount = (int) $request->input('tickets_amount', 20);

            // Валидация
            if ($starsAmount < 50 || $ticketsAmount < 1) {
                return response()->json([
                    'error' => 'Invalid exchange parameters',
                    'message' => 'Минимальная сумма обмена: 50 звёзд'
                ], 400);
            }

            // Создаем запись о транзакции со статусом pending
            $exchange = StarExchange::create([
                'user_id' => $user->id,
                'stars_amount' => $starsAmount,
                'tickets_received' => $ticketsAmount,
                'status' => 'pending',
            ]);

            // Возвращаем данные для открытия Telegram Stars Invoice
            // На фронтенде нужно будет использовать window.Telegram.WebApp.openInvoice()
            return response()->json([
                'success' => true,
                'exchange_id' => $exchange->id,
                'stars_amount' => $starsAmount,
                'tickets_amount' => $ticketsAmount,
                'message' => 'Используйте Telegram Stars Exchange для подтверждения',
            ]);

        } catch (\Exception $e) {
            Log::error('Error initiating star exchange', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Произошла ошибка при инициировании обмена'
            ], 500);
        }
    }

    /**
     * Обработка webhook от Telegram после подтверждения транзакции
     * Этот метод вызывается после успешной оплаты через Telegram Stars
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

            Log::info('Telegram Stars Exchange webhook received', [
                'query_id' => $queryId,
                'payload' => $payload,
                'charge' => $charge,
            ]);

            // Парсим payload для получения exchange_id
            $payloadData = json_decode($payload, true);
            $exchangeId = $payloadData['exchange_id'] ?? null;

            if (!$exchangeId) {
                Log::warning('Exchange ID not found in webhook payload');
                return response()->json([
                    'error' => 'Exchange ID not found'
                ], 400);
            }

            // Находим запись об обмене
            $exchange = StarExchange::find($exchangeId);

            if (!$exchange) {
                Log::warning('Exchange not found', ['exchange_id' => $exchangeId]);
                return response()->json([
                    'error' => 'Exchange not found'
                ], 404);
            }

            // Проверяем, что транзакция еще не обработана
            if ($exchange->status !== 'pending') {
                Log::info('Exchange already processed', [
                    'exchange_id' => $exchangeId,
                    'status' => $exchange->status,
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Exchange already processed'
                ]);
            }

            DB::beginTransaction();

            try {
                // Обновляем статус транзакции
                $exchange->status = 'completed';
                $exchange->transaction_id = $queryId;
                $exchange->save();

                // Начисляем билеты пользователю
                $user = $exchange->user;
                $user->tickets_available += $exchange->tickets_received;
                $user->tickets_available = min($user->tickets_available, 20); // Максимум билетов можно увеличить при обмене
                $user->stars_balance += $exchange->stars_amount; // Увеличиваем баланс звёзд
                $user->save();

                // Создаем записи в user_tickets для отслеживания источника
                \App\Models\UserTicket::create([
                    'user_id' => $user->id,
                    'tickets_count' => $exchange->tickets_received,
                    'restored_at' => now(),
                    'source' => 'star_exchange',
                ]);

                DB::commit();

                Log::info('Star exchange completed successfully', [
                    'exchange_id' => $exchangeId,
                    'user_id' => $user->id,
                    'tickets_received' => $exchange->tickets_received,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Exchange completed successfully',
                    'tickets_added' => $exchange->tickets_received,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                
                // Помечаем транзакцию как failed
                $exchange->status = 'failed';
                $exchange->save();

                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error processing star exchange webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Произошла ошибка при обработке транзакции'
            ], 500);
        }
    }

    /**
     * Подтверждение транзакции от фронтенда (после успешной оплаты)
     * Этот метод вызывается, когда пользователь подтверждает оплату на фронтенде
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function confirmExchange(Request $request): JsonResponse
    {
        try {
            $initData = $request->header('X-Telegram-Init-Data') ?? $request->query('initData');
            
            if (!$initData) {
                return response()->json([
                    'error' => 'Init data not provided'
                ], 401);
            }

            $telegramId = TelegramService::getTelegramId($initData);
            $exchangeId = $request->input('exchange_id');
            $transactionId = $request->input('transaction_id'); // ID транзакции от Telegram

            if (!$exchangeId) {
                return response()->json([
                    'error' => 'Exchange ID not provided'
                ], 400);
            }

            $exchange = StarExchange::find($exchangeId);

            if (!$exchange) {
                return response()->json([
                    'error' => 'Exchange not found'
                ], 404);
            }

            // Проверяем, что обмен принадлежит этому пользователю
            if ($exchange->user->telegram_id != $telegramId) {
                return response()->json([
                    'error' => 'Unauthorized'
                ], 403);
            }

            // Проверяем, что транзакция еще не обработана
            if ($exchange->status !== 'pending') {
                return response()->json([
                    'success' => true,
                    'message' => 'Exchange already processed',
                    'tickets_available' => $exchange->user->tickets_available,
                ]);
            }

            DB::beginTransaction();

            try {
                // Обновляем статус транзакции
                $exchange->status = 'completed';
                if ($transactionId) {
                    $exchange->transaction_id = $transactionId;
                }
                $exchange->save();

                // Начисляем билеты пользователю
                $user = $exchange->user;
                $user->tickets_available += $exchange->tickets_received;
                $user->tickets_available = min($user->tickets_available, 20); // Можно увеличить максимум при обмене
                $user->stars_balance += $exchange->stars_amount;
                $user->save();

                // Создаем записи в user_tickets
                \App\Models\UserTicket::create([
                    'user_id' => $user->id,
                    'tickets_count' => $exchange->tickets_received,
                    'restored_at' => now(),
                    'source' => 'star_exchange',
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Билеты успешно начислены!',
                    'tickets_added' => $exchange->tickets_received,
                    'tickets_available' => $user->tickets_available,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                
                $exchange->status = 'failed';
                $exchange->save();

                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error confirming star exchange', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Произошла ошибка при начислении билетов'
            ], 500);
        }
    }

    /**
     * Получить историю обменов пользователя
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getHistory(Request $request): JsonResponse
    {
        try {
            $initData = $request->header('X-Telegram-Init-Data') ?? $request->query('initData');
            
            if (!$initData) {
                return response()->json([
                    'exchanges' => []
                ]);
            }

            $telegramId = TelegramService::getTelegramId($initData);
            
            if (!$telegramId) {
                return response()->json([
                    'exchanges' => []
                ]);
            }

            $user = User::where('telegram_id', $telegramId)->first();
            
            if (!$user) {
                return response()->json([
                    'exchanges' => []
                ]);
            }

            $exchanges = StarExchange::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return response()->json([
                'exchanges' => $exchanges->map(function ($exchange) {
                    return [
                        'id' => $exchange->id,
                        'stars_amount' => $exchange->stars_amount,
                        'tickets_received' => $exchange->tickets_received,
                        'status' => $exchange->status,
                        'created_at' => $exchange->created_at->toISOString(),
                    ];
                }),
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting star exchange history', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'exchanges' => []
            ]);
        }
    }
}

