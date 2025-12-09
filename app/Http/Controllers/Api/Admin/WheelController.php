<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\WheelSector;
use App\Models\WheelSetting;
use App\Models\PrizeType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class WheelController extends Controller
{
    /**
     * Получить все секторы рулетки и настройки
     */
    public function index(): JsonResponse
    {
        $sectors = WheelSector::with('prizeType')->orderBy('sector_number')->get();

        $totalProbability = $sectors->sum('probability_percent');
        
        $settings = WheelSetting::getSettings();

        return response()->json([
            'data' => $sectors,
            'total_probability' => (float) $totalProbability,
            'settings' => [
                'always_empty_mode' => $settings->always_empty_mode,
                'ticket_restore_hours' => $settings->ticket_restore_hours ?? 3,
                'admin_username' => $settings->admin_username,
            ],
        ]);
    }

    /**
     * Обновить сектор
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $sector = WheelSector::findOrFail($id);

        // Валидация с учетом типа приза
        $validationRules = [
            'prize_type' => 'required|in:money,ticket,secret_box,empty,gift,sponsor_gift',
            'icon_url' => 'nullable|string|max:500',
            'probability_percent' => 'required|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
            'prize_type_id' => 'nullable|exists:prize_types,id',
        ];
        
        // Для типа 'ticket' - максимальное значение 10 билетов
        // Это предотвратит создание призов типа "500 билетов" если это не задано явно
        if ($request->prize_type === 'ticket') {
            $validationRules['prize_value'] = 'nullable|integer|min:0|max:10';
        } else {
            $validationRules['prize_value'] = 'nullable|integer|min:0';
        }
        
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updateData = [
            'prize_type' => $request->prize_type,
            'prize_value' => $request->prize_value ?? 0,
            'icon_url' => $request->icon_url,
            'probability_percent' => $request->probability_percent,
            'is_active' => $request->is_active ?? $sector->is_active,
        ];

        if ($request->has('prize_type_id')) {
            $updateData['prize_type_id'] = $request->prize_type_id;
        }

        $sector->update($updateData);

        // Пересчитываем общую вероятность
        $totalProbability = WheelSector::sum('probability_percent');

        return response()->json([
            'data' => $sector,
            'total_probability' => (float) $totalProbability,
            'message' => 'Сектор успешно обновлен',
        ]);
    }

    /**
     * Массовое обновление секторов
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        // Валидация с проверкой максимального значения билетов
        $validator = Validator::make($request->all(), [
            'sectors' => 'required|array',
            'sectors.*.id' => 'required|exists:wheel_sectors,id',
            'sectors.*.prize_type' => 'required|in:money,ticket,secret_box,empty,gift,sponsor_gift',
            'sectors.*.prize_value' => [
                'nullable',
                'integer',
                'min:0',
                function ($attribute, $value, $fail) use ($request) {
                    // Определяем индекс сектора из атрибута (например, "sectors.0.prize_type")
                    $attributeParts = explode('.', $attribute);
                    $sectorIndex = isset($attributeParts[1]) ? (int)$attributeParts[1] : null;
                    
                    if ($sectorIndex !== null && isset($request->sectors[$sectorIndex])) {
                        $prizeType = $request->sectors[$sectorIndex]['prize_type'] ?? null;
                        // Для типа 'ticket' - максимальное значение 10 билетов
                        // Это предотвратит создание призов типа "500 билетов" если это не задано явно
                        if ($prizeType === 'ticket' && $value !== null && $value > 10) {
                            $fail('Максимальное количество билетов для приза: 10. Если нужен приз с большим количеством билетов, создайте его явно через админку.');
                        }
                    }
                },
            ],
            'sectors.*.icon_url' => 'nullable|string|max:500',
            'sectors.*.probability_percent' => 'required|numeric|min:0|max:100',
            'sectors.*.is_active' => 'nullable|boolean',
            'sectors.*.prize_type_id' => 'nullable|exists:prize_types,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            foreach ($request->sectors as $sectorData) {
                $updateData = [
                    'prize_type' => $sectorData['prize_type'],
                    'prize_value' => $sectorData['prize_value'] ?? 0,
                    'icon_url' => $sectorData['icon_url'] ?? null,
                    'probability_percent' => $sectorData['probability_percent'],
                    'is_active' => $sectorData['is_active'] ?? true,
                ];

                if (isset($sectorData['prize_type_id'])) {
                    $updateData['prize_type_id'] = $sectorData['prize_type_id'];
                }

                WheelSector::where('id', $sectorData['id'])->update($updateData);
            }

            DB::commit();

            $totalProbability = WheelSector::sum('probability_percent');

            return response()->json([
                'message' => 'Секторы успешно обновлены',
                'total_probability' => (float) $totalProbability,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ошибка при обновлении секторов',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Проверить, что сумма вероятностей = 100%
     */
    public function validateProbabilities(): JsonResponse
    {
        $totalProbability = WheelSector::sum('probability_percent');
        $isValid = abs($totalProbability - 100) < 0.01; // Допускаем небольшую погрешность

        return response()->json([
            'is_valid' => $isValid,
            'total_probability' => (float) $totalProbability,
            'difference' => (float) (100 - $totalProbability),
        ]);
    }

    /**
     * Обновить настройки рулетки
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'always_empty_mode' => 'nullable|boolean',
            'ticket_restore_hours' => 'nullable|integer|min:1|max:24',
            'admin_username' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updateData = [];
        
        if ($request->has('always_empty_mode')) {
            $updateData['always_empty_mode'] = $request->always_empty_mode;
        }
        
        if ($request->has('ticket_restore_hours')) {
            $updateData['ticket_restore_hours'] = $request->ticket_restore_hours;
        }
        
        if ($request->has('admin_username')) {
            $updateData['admin_username'] = $request->admin_username;
        }

        if (empty($updateData)) {
            return response()->json([
                'message' => 'Нет данных для обновления',
            ], 422);
        }

        $settings = WheelSetting::updateSettings($updateData);

        return response()->json([
            'message' => 'Настройки успешно обновлены',
            'settings' => [
                'always_empty_mode' => $settings->always_empty_mode,
                'ticket_restore_hours' => $settings->ticket_restore_hours ?? 3,
                'admin_username' => $settings->admin_username,
            ],
        ]);
    }
}

