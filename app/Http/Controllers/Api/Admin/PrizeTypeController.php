<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrizeType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PrizeTypeController extends Controller
{
    /**
     * Получить все типы призов
     */
    public function index(): JsonResponse
    {
        $prizeTypes = PrizeType::orderBy('created_at', 'desc')->get();

        return response()->json([
            'data' => $prizeTypes,
        ]);
    }

    /**
     * Создать новый тип приза
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:money,ticket,gift,secret_box,empty,sponsor_gift',
            'value' => 'nullable|integer|min:0',
            'message' => 'nullable|string',
            'action' => 'nullable|in:none,add_ticket',
            'icon_url' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $prizeType = PrizeType::create([
            'name' => $request->name,
            'type' => $request->type,
            'value' => $request->value ?? 0,
            'message' => $request->message,
            'action' => $request->action ?? 'none',
            'icon_url' => $request->icon_url,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'data' => $prizeType,
            'message' => 'Тип приза успешно создан',
        ], 201);
    }

    /**
     * Получить тип приза
     */
    public function show(int $id): JsonResponse
    {
        $prizeType = PrizeType::findOrFail($id);

        return response()->json([
            'data' => $prizeType,
        ]);
    }

    /**
     * Обновить тип приза
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $prizeType = PrizeType::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:money,ticket,gift,secret_box,empty,sponsor_gift',
            'value' => 'nullable|integer|min:0',
            'message' => 'nullable|string',
            'action' => 'nullable|in:none,add_ticket',
            'icon_url' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $prizeType->update($request->only([
            'name',
            'type',
            'value',
            'message',
            'action',
            'icon_url',
            'is_active',
        ]));

        return response()->json([
            'data' => $prizeType,
            'message' => 'Тип приза успешно обновлен',
        ]);
    }

    /**
     * Удалить тип приза
     */
    public function destroy(int $id): JsonResponse
    {
        $prizeType = PrizeType::findOrFail($id);

        // Проверяем, используется ли тип приза в секторах
        $usedInSectors = $prizeType->wheelSectors()->exists();
        
        if ($usedInSectors) {
            return response()->json([
                'message' => 'Невозможно удалить тип приза, так как он используется в секторах рулетки',
            ], 422);
        }

        $prizeType->delete();

        return response()->json([
            'message' => 'Тип приза успешно удален',
        ]);
    }
}

