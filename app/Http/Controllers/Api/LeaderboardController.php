<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    /**
     * Получить лидерборд ТОП-10 пользователей по приглашениям
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $month = $request->input('month', now()->month);
            $year = $request->input('year', now()->year);
            $limit = min((int) $request->input('limit', 10), 50); // Максимум 50

            // Получаем ТОП пользователей за указанный месяц
            $topUsers = DB::table('referrals')
                ->select(
                    'referrals.inviter_id',
                    DB::raw('COUNT(*) as invites_count'),
                    'users.telegram_id',
                    'users.username',
                    'users.avatar_url'
                )
                ->join('users', 'referrals.inviter_id', '=', 'users.id')
                ->whereYear('referrals.invited_at', $year)
                ->whereMonth('referrals.invited_at', $month)
                ->groupBy('referrals.inviter_id', 'users.telegram_id', 'users.username', 'users.avatar_url')
                ->orderBy('invites_count', 'desc')
                ->limit($limit)
                ->get();

            // Добавляем ранги и призы
            $leaderboard = $topUsers->map(function ($user, $index) {
                $rank = $index + 1;
                $prizeAmount = $this->getPrizeAmount($rank);
                
                return [
                    'rank' => $rank,
                    'telegram_id' => $user->telegram_id,
                    'username' => $user->username ?? "User {$user->telegram_id}",
                    'avatar_url' => $user->avatar_url,
                    'invites_count' => $user->invites_count,
                    'prize_amount' => $prizeAmount,
                ];
            });

            return response()->json([
                'leaderboard' => $leaderboard,
                'month' => $month,
                'year' => $year,
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting leaderboard', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'leaderboard' => [],
                'month' => now()->month,
                'year' => now()->year,
            ]);
        }
    }

    /**
     * Получить размер приза по рангу
     * 
     * @param int $rank
     * @return int
     */
    private function getPrizeAmount(int $rank): int
    {
        return match($rank) {
            1 => 1500, // 1 место - 1500₽
            2 => 1000, // 2 место - 1000₽
            3 => 500,  // 3 место - 500₽
            default => 0, // Остальные без приза
        };
    }
}

