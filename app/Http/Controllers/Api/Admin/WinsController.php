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

        // Форматируем данные для отображения
        $formattedData = $wins->getCollection()->map(function ($spin) {
            return [
                'id' => $spin->id,
                'telegram_id' => $spin->user ? $spin->user->telegram_id : null,
                'username' => $spin->user ? $spin->user->username : null,
                'prize_name' => $this->getPrizeName($spin->prize_type ?? 'empty', $spin->prize_value ?? 0),
                'sector_number' => $spin->sector_number ?? ($spin->sector ? $spin->sector->sector_number : null),
                'spin_time' => $spin->spin_time,
                'prize_type' => $spin->prize_type,
                'prize_value' => $spin->prize_value,
            ];
        });

        return response()->json([
            'data' => $formattedData,
            'pagination' => [
                'current_page' => $wins->currentPage(),
                'last_page' => $wins->lastPage(),
                'per_page' => $wins->perPage(),
                'total' => $wins->total(),
            ],
        ]);
    }

    /**
     * Получить название приза в читаемом формате
     */
    private function getPrizeName(string $prizeType, int $prizeValue): string
    {
        switch ($prizeType) {
            case 'money':
                return $prizeValue . ' рублей';
            case 'ticket':
                return 'плюс ' . $prizeValue . ' билет';
            case 'secret_box':
                return 'WOW Secret Box';
            case 'empty':
            default:
                return 'пусто';
        }
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

