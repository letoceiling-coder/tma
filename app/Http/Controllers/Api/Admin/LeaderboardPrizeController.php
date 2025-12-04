<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaderboardPrize;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class LeaderboardPrizeController extends Controller
{
    /**
     * Получить все призы лидерборда
     */
    public function index(): JsonResponse
    {
        $prizes = LeaderboardPrize::orderBy('rank')->get();
        
        return response()->json([
            'data' => $prizes,
        ]);
    }

    /**
     * Обновить приз
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $prize = LeaderboardPrize::findOrFail($id);

        $validator = Validator::make($request->all(), [
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

        $prize->update([
            'prize_amount' => $request->prize_amount,
            'prize_description' => $request->prize_description ?? $prize->prize_description,
            'is_active' => $request->is_active ?? $prize->is_active,
        ]);

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
            'prizes' => 'required|array',
            'prizes.*.id' => 'required|exists:leaderboard_prizes,id',
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
            LeaderboardPrize::where('id', $prizeData['id'])->update([
                'prize_amount' => $prizeData['prize_amount'],
                'prize_description' => $prizeData['prize_description'] ?? null,
                'is_active' => $prizeData['is_active'] ?? true,
            ]);
        }

        $updatedPrizes = LeaderboardPrize::orderBy('rank')->get();

        return response()->json([
            'message' => 'Призы успешно обновлены',
            'data' => $updatedPrizes,
        ]);
    }
}

