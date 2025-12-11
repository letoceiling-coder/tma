<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Spin;
use App\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WowUserController extends Controller
{
    /**
     * Получить список пользователей WOW
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::whereNotNull('telegram_id')
            ->withCount(['spins', 'referralsAsInviter as invites_count'])
            ->with(['inviter']);

        // Поиск
        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('telegram_id', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Фильтр по дате регистрации
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Сортировка
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Пагинация
        $perPage = min((int) $request->input('per_page', 15), 100);
        $users = $query->paginate($perPage);

        return response()->json($users);
    }

    /**
     * Получить детальную информацию о пользователе
     */
    public function show(int $id): JsonResponse
    {
        $user = User::whereNotNull('telegram_id')
            ->with(['inviter', 'referralsAsInviter.invited'])
            ->withCount([
                'spins',
                'spins as wins_count' => function($query) {
                    $query->where('prize_type', '!=', 'empty')
                          ->where('prize_value', '>', 0);
                },
                'referralsAsInviter as invites_count',
            ])
            ->findOrFail($id);

        // Статистика прокрутов
        $spinsStats = Spin::where('user_id', $user->id)
            ->selectRaw('prize_type, COUNT(*) as count, SUM(prize_value) as total_value')
            ->groupBy('prize_type')
            ->get();

        // Последние прокруты
        $recentSpins = Spin::where('user_id', $user->id)
            ->with('sector')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $user,
            'spins_stats' => $spinsStats,
            'recent_spins' => $recentSpins,
        ]);
    }

    /**
     * Начислить билеты пользователю вручную
     */
    public function addTickets(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'tickets' => 'required|integer|min:1|max:100',
        ]);

        $user = User::whereNotNull('telegram_id')->findOrFail($id);

        $ticketsToAdd = $request->input('tickets');
        $oldTickets = $user->tickets_available;
        
        // Начисляем билеты
        $user->tickets_available = $user->tickets_available + $ticketsToAdd;
        $user->save();

        // Создаем запись в истории билетов
        \App\Models\UserTicket::create([
            'user_id' => $user->id,
            'tickets_count' => $ticketsToAdd,
            'source' => 'admin_grant', // Новый источник для админских начислений
            'restored_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Начислено {$ticketsToAdd} билет(ов)",
            'old_tickets' => $oldTickets,
            'new_tickets' => $user->tickets_available,
            'data' => $user,
        ]);
    }

    /**
     * Списать билеты у пользователя вручную
     */
    public function removeTickets(Request $request, int $id): JsonResponse
    {
        $user = User::whereNotNull('telegram_id')->findOrFail($id);
        
        $oldTickets = $user->tickets_available;
        $maxTickets = max(1, $oldTickets); // Минимум 1 для валидации, максимум - текущее количество
        
        $request->validate([
            'tickets' => [
                'required',
                'integer',
                'min:1',
                "max:{$maxTickets}",
            ],
        ], [
            'tickets.max' => "Недостаточно билетов. У пользователя: {$oldTickets}, можно списать не более {$maxTickets}",
        ]);

        $ticketsToRemove = $request->input('tickets');
        
        // Дополнительная проверка на всякий случай
        if ($ticketsToRemove > $oldTickets) {
            return response()->json([
                'success' => false,
                'message' => "Недостаточно билетов. У пользователя: {$oldTickets}, запрошено списание: {$ticketsToRemove}",
            ], 400);
        }
        
        // Списываем билеты
        $user->tickets_available = max(0, $user->tickets_available - $ticketsToRemove);
        $user->save();

        // Создаем запись в истории билетов
        \App\Models\UserTicket::create([
            'user_id' => $user->id,
            'tickets_count' => -$ticketsToRemove, // Отрицательное значение для списания
            'source' => 'admin_remove', // Новый источник для админских списаний
            'restored_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Списано {$ticketsToRemove} билет(ов)",
            'old_tickets' => $oldTickets,
            'new_tickets' => $user->tickets_available,
            'data' => $user,
        ]);
    }
}

