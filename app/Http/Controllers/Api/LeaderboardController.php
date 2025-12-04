<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\User;
use App\Models\LeaderboardPrize;
use App\Models\WheelSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    /**
     * Получить лидерборд ТОП-10 пользователей по приглашениям
     * 
     * Использует таблицу referrals, если она заполнена,
     * иначе использует поле invited_by из таблицы users
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $limit = min((int) $request->input('limit', 10), 50); // Максимум 50

            // Получаем период из настроек
            $settings = WheelSetting::getSettings();
            $periodMonths = $settings->leaderboard_period_months ?? 1;
            
            // Вычисляем дату начала периода
            $periodStartDate = now()->subMonths($periodMonths)->startOfDay();

            // Проверяем, есть ли данные в таблице referrals
            $referralsCount = Referral::count();
            
            if ($referralsCount > 0) {
                // Используем таблицу referrals (основной источник)
                $topUsers = DB::table('referrals')
                    ->select(
                        'referrals.inviter_id',
                        DB::raw('COUNT(*) as invites_count'),
                        'users.telegram_id',
                        'users.username',
                        'users.avatar_url'
                    )
                    ->join('users', 'referrals.inviter_id', '=', 'users.id')
                    ->where('referrals.invited_at', '>=', $periodStartDate)
                    ->groupBy('referrals.inviter_id', 'users.telegram_id', 'users.username', 'users.avatar_url')
                    ->orderBy('invites_count', 'desc')
                    ->limit($limit)
                    ->get();
            } else {
                // Если таблица referrals пустая, используем поле invited_by из users
                $topUsers = DB::table('users')
                    ->select(
                        'users.id as inviter_id',
                        DB::raw('COUNT(invited_users.id) as invites_count'),
                        'users.telegram_id',
                        'users.username',
                        'users.avatar_url'
                    )
                    ->join('users as invited_users', 'users.id', '=', 'invited_users.invited_by')
                    ->whereNotNull('invited_users.invited_by')
                    ->where('invited_users.created_at', '>=', $periodStartDate)
                    ->groupBy('users.id', 'users.telegram_id', 'users.username', 'users.avatar_url')
                    ->orderBy('invites_count', 'desc')
                    ->limit($limit)
                    ->get();
            }

            // Добавляем ранги и призы
            $leaderboard = $topUsers->map(function ($user, $index) {
                $rank = $index + 1;
                $prizeAmount = $this->getPrizeAmount($rank);
                
                return [
                    'rank' => $rank,
                    'telegram_id' => $user->telegram_id,
                    'username' => $user->username ?? "User {$user->telegram_id}",
                    'avatar_url' => $user->avatar_url,
                    'invites_count' => (int) $user->invites_count,
                    'prize_amount' => $prizeAmount,
                ];
            });

            return response()->json([
                'leaderboard' => $leaderboard,
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error getting leaderboard', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'leaderboard' => [],
            ]);
        }
    }

    /**
     * Получить размер приза по рангу (из настроек БД)
     * 
     * @param int $rank
     * @return int
     */
    private function getPrizeAmount(int $rank): int
    {
        $prize = LeaderboardPrize::getPrizeForRank($rank);
        return $prize ? $prize->prize_amount : 0;
    }
}

