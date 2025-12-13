<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\WheelError;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WheelErrorController extends Controller
{
    /**
     * Получить список ошибок рулетки
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = min((int) $request->get('per_page', 50), 200); // Максимум 200 на страницу
            $errorType = $request->get('error_type');
            $prizeType = $request->get('prize_type');
            $sectorId = $request->get('sector_id');
            $userId = $request->get('user_id');
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');

            $query = WheelError::with(['user', 'sector'])
                ->orderBy('timestamp', 'desc')
                ->orderBy('id', 'desc');

            // Фильтры
            if ($errorType) {
                $query->where('error_type', $errorType);
            }

            if ($prizeType) {
                $query->where('prize_type', $prizeType);
            }

            if ($sectorId) {
                $query->where('sector_id', $sectorId);
            }

            if ($userId) {
                $query->where('user_id', $userId);
            }

            if ($dateFrom) {
                $query->where('timestamp', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->where('timestamp', '<=', $dateTo);
            }

            $errors = $query->paginate($perPage);

            // Статистика по типам ошибок
            $stats = WheelError::selectRaw('error_type, COUNT(*) as count')
                ->groupBy('error_type')
                ->orderBy('count', 'desc')
                ->get()
                ->pluck('count', 'error_type');

            return response()->json([
                'success' => true,
                'data' => $errors,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching wheel errors', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка ошибок',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Получить детали ошибки
     */
    public function show(int $id): JsonResponse
    {
        try {
            $error = WheelError::with(['user', 'sector'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $error,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching wheel error details', [
                'error_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка не найдена',
            ], 404);
        }
    }

    /**
     * Получить статистику ошибок по сектору sponsor_gift
     */
    public function sponsorGiftStats(): JsonResponse
    {
        try {
            $stats = WheelError::where(function ($query) {
                    $query->where('prize_type', 'sponsor_gift')
                        ->orWhereHas('sector', function ($q) {
                            $q->where('prize_type', 'sponsor_gift');
                        });
                })
                ->selectRaw('
                    error_type,
                    COUNT(*) as count_by_type,
                    COUNT(DISTINCT user_id) as affected_users
                ')
                ->groupBy('error_type')
                ->get();

            $totalStats = WheelError::where(function ($query) {
                    $query->where('prize_type', 'sponsor_gift')
                        ->orWhereHas('sector', function ($q) {
                            $q->where('prize_type', 'sponsor_gift');
                        });
                })
                ->selectRaw('
                    COUNT(*) as total_errors,
                    COUNT(DISTINCT user_id) as affected_users,
                    MIN(timestamp) as first_error,
                    MAX(timestamp) as last_error
                ')
                ->first();

            $recentErrors = WheelError::where(function ($query) {
                    $query->where('prize_type', 'sponsor_gift')
                        ->orWhereHas('sector', function ($q) {
                            $q->where('prize_type', 'sponsor_gift');
                        });
                })
                ->with(['user', 'sector'])
                ->orderBy('timestamp', 'desc')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'total_stats' => $totalStats,
                'stats_by_type' => $stats,
                'recent_errors' => $recentErrors,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching sponsor_gift stats', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статистики',
            ], 500);
        }
    }
}
