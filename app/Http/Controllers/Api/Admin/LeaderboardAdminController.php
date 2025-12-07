<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Spin;
use App\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LeaderboardAdminController extends Controller
{
    /**
     * Получить топ пользователей для админки с расширенной статистикой
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        // Подсчитываем статистику для каждого пользователя
        $query->withCount([
            'spins as wins_count' => function ($q) {
                $q->where('prize_type', '!=', 'empty');
            },
            'invitedUsers as invites_count',
        ]);

        // Подсчитываем сумму выигрышей
        $query->withSum([
            'spins as total_wins_amount' => function ($q) {
                $q->where('prize_type', 'money');
            }
        ], 'prize_value');

        // Подсчитываем количество прокрутов
        $query->withCount('spins as spins_count');

        // Фильтрация по периоду
        $period = $request->input('period', 'all'); // day, week, all
        
        if ($period === 'day') {
            $periodStartDate = now()->startOfDay();
            $query->whereHas('spins', function ($q) use ($periodStartDate) {
                $q->where('spin_time', '>=', $periodStartDate);
            });
        } elseif ($period === 'week') {
            $periodStartDate = now()->startOfWeek();
            $query->whereHas('spins', function ($q) use ($periodStartDate) {
                $q->where('spin_time', '>=', $periodStartDate);
            });
        }

        // Сортировка
        $sortBy = $request->input('sort_by', 'wins_count'); // wins_count, total_wins_amount, spins_count, invites_count
        $sortOrder = $request->input('sort_order', 'desc'); // asc, desc
        
        $validSortFields = ['wins_count', 'total_wins_amount', 'spins_count', 'invites_count', 'created_at'];
        if (!in_array($sortBy, $validSortFields)) {
            $sortBy = 'wins_count';
        }
        
        $query->orderBy($sortBy, $sortOrder);

        // Пагинация
        $perPage = $request->get('per_page', 50);
        $users = $query->paginate($perPage);

        return response()->json([
            'data' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }
}

