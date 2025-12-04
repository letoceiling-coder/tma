<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaderboardPrize;
use App\Models\WheelSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class LeaderboardPrizeController extends Controller
{
    /**
     * Получить все призы лидерборда и настройки
     */
    public function index(): JsonResponse
    {
        $prizes = LeaderboardPrize::orderBy('rank')->get();
        $settings = WheelSetting::getSettings();
        
        return response()->json([
            'data' => $prizes,
            'leaderboard_period_months' => $settings->leaderboard_period_months ?? 1,
        ]);
    }

    /**
     * Обновить приз
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $prize = LeaderboardPrize::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'rank' => 'sometimes|integer|min:1|unique:leaderboard_prizes,rank,' . $id,
            'prize_amount' => 'required|integer|min:0|max:1000000',
            'prize_description' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updateData = [
            'prize_amount' => $request->prize_amount,
            'prize_description' => $request->prize_description ?? $prize->prize_description,
            'is_active' => $request->is_active ?? $prize->is_active,
        ];

        if ($request->has('rank')) {
            $updateData['rank'] = $request->rank;
        }

        $prize->update($updateData);

        return response()->json([
            'message' => 'Приз успешно обновлен',
            'data' => $prize,
        ]);
    }

    /**
     * Массовое обновление призов
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'prizes' => 'required|array|min:1',
            'prizes.*.id' => 'required|exists:leaderboard_prizes,id',
            'prizes.*.rank' => 'sometimes|integer|min:1',
            'prizes.*.prize_amount' => 'required|integer|min:0|max:1000000',
            'prizes.*.prize_description' => 'nullable|string|max:255',
            'prizes.*.is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        foreach ($request->prizes as $prizeData) {
            $updateData = [
                'prize_amount' => $prizeData['prize_amount'],
                'prize_description' => $prizeData['prize_description'] ?? null,
                'is_active' => $prizeData['is_active'] ?? true,
            ];

            // Обновляем rank, если он указан
            if (isset($prizeData['rank'])) {
                // Проверяем уникальность rank для других записей
                $existingPrize = LeaderboardPrize::where('rank', $prizeData['rank'])
                    ->where('id', '!=', $prizeData['id'])
                    ->first();
                
                if ($existingPrize) {
                    continue; // Пропускаем, если место уже занято
                }
                
                $updateData['rank'] = $prizeData['rank'];
            }

            LeaderboardPrize::where('id', $prizeData['id'])->update($updateData);
        }

        $updatedPrizes = LeaderboardPrize::orderBy('rank')->get();

        return response()->json([
            'message' => 'Призы успешно обновлены',
            'data' => $updatedPrizes,
        ]);
    }

    /**
     * Создать новый приз
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rank' => 'required|integer|min:1|unique:leaderboard_prizes,rank',
            'prize_amount' => 'required|integer|min:0|max:1000000',
            'prize_description' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $prize = LeaderboardPrize::create([
            'rank' => $request->rank,
            'prize_amount' => $request->prize_amount,
            'prize_description' => $request->prize_description ?? null,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'message' => 'Приз успешно создан',
            'data' => $prize,
        ], 201);
    }

    /**
     * Удалить приз
     */
    public function destroy(int $id): JsonResponse
    {
        $prize = LeaderboardPrize::findOrFail($id);
        $prize->delete();

        return response()->json([
            'message' => 'Приз успешно удален',
        ]);
    }

    /**
     * Обновить период отображения лидерборда
     */
    public function updatePeriod(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'leaderboard_period_months' => 'required|integer|in:1,2,3,4,5,6,12',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $settings = WheelSetting::updateSettings([
            'leaderboard_period_months' => $request->leaderboard_period_months,
        ]);

        return response()->json([
            'message' => 'Период лидерборда успешно обновлен',
            'leaderboard_period_months' => $settings->leaderboard_period_months,
        ]);
    }
}

