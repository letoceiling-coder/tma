<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Spin;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WinsController extends Controller
{
    /**
     * Получить список всех выигрышей с фильтрацией
     */
    public function index(Request $request): JsonResponse
    {
        $query = Spin::with(['user', 'sector'])
            ->orderBy('spin_time', 'desc');

        // Фильтрация по дате
        if ($request->has('date_from')) {
            $query->where('spin_time', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('spin_time', '<=', $request->date_to . ' 23:59:59');
        }

        // Фильтрация по пользователю (Telegram ID или username)
        if ($request->has('user_search')) {
            $search = $request->user_search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('telegram_id', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        // Фильтрация по типу приза
        if ($request->has('prize_type') && $request->prize_type !== 'all') {
            $query->where('prize_type', $request->prize_type);
        }

        // Пагинация
        $perPage = $request->get('per_page', 50);
        $wins = $query->paginate($perPage);

        return response()->json([
            'data' => $wins->items(),
            'pagination' => [
                'current_page' => $wins->currentPage(),
                'last_page' => $wins->lastPage(),
                'per_page' => $wins->perPage(),
                'total' => $wins->total(),
            ],
        ]);
    }

    /**
     * Получить статистику по выигрышам
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_wins' => Spin::where('prize_type', '!=', 'empty')->count(),
            'total_money' => Spin::where('prize_type', 'money')->sum('prize_value'),
            'total_tickets' => Spin::where('prize_type', 'ticket')->sum('prize_value'),
            'total_secret_boxes' => Spin::where('prize_type', 'secret_box')->count(),
            'by_type' => Spin::select('prize_type', DB::raw('count(*) as count'))
                ->where('prize_type', '!=', 'empty')
                ->groupBy('prize_type')
                ->get()
                ->pluck('count', 'prize_type'),
        ];

        return response()->json($stats);
    }
}

