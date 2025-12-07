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
use Illuminate\Support\Facades\Log;

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

            // Поддержка фильтрации по периоду: day, week, all
            $period = $request->input('period', 'all'); // day, week, all
            
            $periodStartDate = null;
            if ($period === 'day') {
                $periodStartDate = now()->startOfDay();
            } elseif ($period === 'week') {
                $periodStartDate = now()->startOfWeek();
            } elseif ($period === 'all') {
                // Для "всё время" не применяем фильтрацию по дате
                $periodStartDate = null;
            } else {
                // Если период не указан или неверный, используем настройки по умолчанию
                $settings = WheelSetting::getSettings();
                $periodMonths = $settings->leaderboard_period_months ?? 1;
                $periodStartDate = now()->subMonths($periodMonths)->startOfDay();
            }

            // Проверяем, есть ли данные в таблице referrals
            $referralsCount = Referral::count();
            $usersWithInvitedBy = User::whereNotNull('invited_by')->count();
            
            Log::info('Leaderboard data check', [
                'referrals_count' => $referralsCount,
                'users_with_invited_by' => $usersWithInvitedBy,
                'period_months' => $periodMonths,
                'period_start_date' => $periodStartDate->toIso8601String(),
            ]);
            
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
                    ->join('users', 'referrals.inviter_id', '=', 'users.id');
                
                if ($periodStartDate) {
                    $topUsers->where('referrals.invited_at', '>=', $periodStartDate);
                }
                
                $topUsers = $topUsers
                    ->groupBy('referrals.inviter_id', 'users.telegram_id', 'users.username', 'users.avatar_url')
                    ->orderBy('invites_count', 'desc')
                    ->limit($limit)
                    ->get();
                
                Log::info('Leaderboard from referrals table', [
                    'users_count' => $topUsers->count(),
                ]);
            } else {
                // Если таблица referrals пустая, используем поле invited_by из users
                // ВАЖНО: Убираем фильтрацию по дате, если нет данных в referrals
                // Показываем всех пользователей с рефералами
                $topUsers = DB::table('users')
                    ->select(
                        'users.id as inviter_id',
                        DB::raw('COUNT(invited_users.id) as invites_count'),
                        'users.telegram_id',
                        'users.username',
                        'users.avatar_url'
                    )
                    ->join('users as invited_users', 'users.id', '=', 'invited_users.invited_by')
                    ->whereNotNull('invited_users.invited_by');
                
                // Применяем фильтрацию по дате только если период установлен
                if ($periodStartDate) {
                    $topUsers->where('invited_users.created_at', '>=', $periodStartDate);
                }
                
                $topUsers = $topUsers
                    ->groupBy('users.id', 'users.telegram_id', 'users.username', 'users.avatar_url')
                    ->orderBy('invites_count', 'desc')
                    ->limit($limit)
                    ->get();
                
                Log::info('Leaderboard from users.invited_by', [
                    'users_count' => $topUsers->count(),
                    'period_filter_applied' => $periodMonths > 0,
                ]);
            }

            // Добавляем ранги и призы
            // ВАЖНО: Показываем только те места, для которых есть пользователи
            // Если пользователей меньше, чем настроено мест, список сокращается
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

            // Список автоматически сокращается до количества пользователей
            // Если пользователей 3, показываем только 3 места, даже если настроено 10

            return response()->json([
                'leaderboard' => $leaderboard,
                'period' => $period,
                'period_start_date' => $periodStartDate?->toIso8601String(),
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

