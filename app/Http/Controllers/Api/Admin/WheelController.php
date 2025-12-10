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
use Illuminate\Support\Facades\Log;

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
                'initial_tickets_count' => $settings->initial_tickets_count ?? 1,
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
        try {
            // Логируем входящие данные для отладки
            Log::info('Bulk update sectors request', [
                'sectors_count' => count($request->input('sectors', [])),
                'first_sector' => $request->input('sectors.0', []),
            ]);

            // Нормализуем данные перед валидацией
            // Если prize_type пришел как объект, извлекаем строковое значение
            $normalizedSectors = [];
            foreach ($request->input('sectors', []) as $index => $sector) {
                $normalizedSector = is_array($sector) ? $sector : [];
                
                // Если prize_type - объект/массив, извлекаем строковое значение
                $prizeType = $sector['prize_type'] ?? null;
                if (is_array($prizeType)) {
                    // Если это объект с полем 'type', используем его
                    $normalizedSector['prize_type'] = $prizeType['type'] ?? $prizeType['prize_type'] ?? 'empty';
                } elseif (is_string($prizeType)) {
                    // Если это уже строка, оставляем как есть
                    $normalizedSector['prize_type'] = $prizeType;
                } else {
                    // Если prize_type не определен, используем значение из prizeType relation или 'empty'
                    // Если есть prize_type_id, пытаемся получить тип из связанного PrizeType
                    if (isset($sector['prize_type_id']) && $sector['prize_type_id']) {
                        $prizeTypeModel = \App\Models\PrizeType::find($sector['prize_type_id']);
                        $normalizedSector['prize_type'] = $prizeTypeModel ? $prizeTypeModel->type : 'empty';
                    } else {
                        $normalizedSector['prize_type'] = 'empty';
                    }
                }
                
                // Сохраняем остальные поля
                $normalizedSector['id'] = $sector['id'] ?? null;
                $normalizedSector['prize_value'] = $sector['prize_value'] ?? 0;
                $normalizedSector['icon_url'] = $sector['icon_url'] ?? null;
                $normalizedSector['probability_percent'] = $sector['probability_percent'] ?? 0;
                $normalizedSector['is_active'] = $sector['is_active'] ?? true;
                $normalizedSector['prize_type_id'] = $sector['prize_type_id'] ?? null;
                
                $normalizedSectors[] = $normalizedSector;
            }
            
            // Заменяем sectors в request на нормализованные данные
            $request->merge(['sectors' => $normalizedSectors]);
            
            // Логируем нормализованные данные для отладки
            Log::debug('Normalized sectors for validation', [
                'first_sector_normalized' => $normalizedSectors[0] ?? null,
            ]);

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
                Log::warning('Bulk update validation failed', [
                    'errors' => $validator->errors()->toArray(),
                ]);
                return response()->json([
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            try {
                foreach ($request->sectors as $index => $sectorData) {
                    try {
                        // Преобразуем типы данных
                        $updateData = [
                            'prize_type' => $sectorData['prize_type'],
                            'prize_value' => isset($sectorData['prize_value']) && $sectorData['prize_value'] !== '' && $sectorData['prize_value'] !== null 
                                ? (int)$sectorData['prize_value'] 
                                : 0,
                            'icon_url' => $sectorData['icon_url'] ?? null,
                            'probability_percent' => isset($sectorData['probability_percent']) 
                                ? (float)$sectorData['probability_percent'] 
                                : 0,
                            'is_active' => isset($sectorData['is_active']) 
                                ? (bool)$sectorData['is_active'] 
                                : true,
                        ];

                        if (isset($sectorData['prize_type_id']) && $sectorData['prize_type_id'] !== null && $sectorData['prize_type_id'] !== '') {
                            $updateData['prize_type_id'] = (int)$sectorData['prize_type_id'];
                        } else {
                            $updateData['prize_type_id'] = null;
                        }

                        $affected = WheelSector::where('id', $sectorData['id'])->update($updateData);
                        
                        if ($affected === 0) {
                            Log::warning('Sector not updated', [
                                'sector_id' => $sectorData['id'],
                                'update_data' => $updateData,
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error('Error updating sector', [
                            'sector_index' => $index,
                            'sector_data' => $sectorData,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        throw $e;
                    }
                }

                DB::commit();

                $totalProbability = WheelSector::sum('probability_percent');

                Log::info('Bulk update sectors completed', [
                    'total_probability' => $totalProbability,
                ]);

                return response()->json([
                    'message' => 'Секторы успешно обновлены',
                    'total_probability' => (float) $totalProbability,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Bulk update sectors failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return response()->json([
                    'message' => 'Ошибка при обновлении секторов',
                    'error' => config('app.debug') ? $e->getMessage() : 'Внутренняя ошибка сервера',
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Bulk update sectors exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'message' => 'Ошибка при обновлении секторов',
                'error' => config('app.debug') ? $e->getMessage() : 'Внутренняя ошибка сервера',
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
            'initial_tickets_count' => 'nullable|integer|min:0|max:100',
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
        
        if ($request->has('initial_tickets_count')) {
            $updateData['initial_tickets_count'] = $request->initial_tickets_count;
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
                'initial_tickets_count' => $settings->initial_tickets_count ?? 1,
            ],
        ]);
    }
}

