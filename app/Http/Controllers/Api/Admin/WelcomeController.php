<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\WheelSetting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class WelcomeController extends Controller
{
    /**
     * Получить настройки приветствия
     */
    public function index(): JsonResponse
    {
        $settings = WheelSetting::getSettings();
        
        // Дефолтные кнопки, если не заданы
        $defaultButtons = [
            ['label' => 'Наш канал', 'url' => 'https://t.me/WowSpin_news'],
            ['label' => 'Менеджер', 'url' => 'https://t.me/wows_manager'],
        ];
        
        return response()->json([
            'welcome_text' => $settings->welcome_text,
            'welcome_banner_url' => $settings->welcome_banner_url,
            'welcome_buttons' => $settings->welcome_buttons ?? $defaultButtons,
        ]);
    }

    /**
     * Обновить настройки приветствия
     */
    public function update(Request $request): JsonResponse
    {
        // Нормализуем данные перед валидацией
        $data = $request->all();
        
        // Обрабатываем welcome_buttons - если это null или пустой массив, не валидируем
        if (isset($data['welcome_buttons']) && (is_null($data['welcome_buttons']) || (is_array($data['welcome_buttons']) && empty($data['welcome_buttons'])))) {
            $data['welcome_buttons'] = null;
        }
        
        // Фильтруем пустые кнопки перед валидацией
        if (isset($data['welcome_buttons']) && is_array($data['welcome_buttons'])) {
            $data['welcome_buttons'] = array_values(array_filter($data['welcome_buttons'], function($button) {
                return !empty($button['label']) && !empty($button['url']);
            }));
            
            // Если после фильтрации массив пуст, устанавливаем null
            if (empty($data['welcome_buttons'])) {
                $data['welcome_buttons'] = null;
            }
        }
        
        $validator = Validator::make($data, [
            'welcome_text' => 'nullable|string|max:4096',
            'welcome_banner_url' => [
                'nullable',
                'string',
                'max:500',
                function ($attribute, $value, $fail) {
                    if (empty($value)) {
                        return;
                    }
                    // Проверяем, что это либо полный URL, либо относительный путь
                    if (!filter_var($value, FILTER_VALIDATE_URL) && !preg_match('/^\/[^\/]/', $value)) {
                        $fail('Поле :attribute должно быть валидным URL или относительным путем.');
                    }
                },
            ],
            'welcome_buttons' => 'nullable|array|max:5',
            'welcome_buttons.*.label' => 'required|string|max:64',
            'welcome_buttons.*.url' => [
                'required',
                'string',
                'max:500',
                function ($attribute, $value, $fail) {
                    if (empty($value)) {
                        return;
                    }
                    // Проверяем, что это валидный URL
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        $fail('Поле :attribute должно быть валидным URL.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            Log::warning('Welcome settings validation failed', [
                'errors' => $validator->errors()->toArray(),
                'data' => $data,
            ]);
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updateData = [];
        
        if ($request->has('welcome_text')) {
            $updateData['welcome_text'] = $request->welcome_text ?: null;
        }
        
        if ($request->has('welcome_banner_url')) {
            $updateData['welcome_banner_url'] = $request->welcome_banner_url ?: null;
        }
        
        if ($request->has('welcome_buttons')) {
            $updateData['welcome_buttons'] = $data['welcome_buttons'];
        }

        if (empty($updateData)) {
            return response()->json([
                'message' => 'Нет данных для обновления',
            ], 422);
        }

        $settings = WheelSetting::updateSettings($updateData);

        Log::info('Welcome settings updated', [
            'has_text' => !empty($settings->welcome_text),
            'has_banner' => !empty($settings->welcome_banner_url),
            'buttons_count' => is_array($settings->welcome_buttons) ? count($settings->welcome_buttons) : 0,
        ]);

        return response()->json([
            'message' => 'Настройки приветствия успешно обновлены',
            'settings' => [
                'welcome_text' => $settings->welcome_text,
                'welcome_banner_url' => $settings->welcome_banner_url,
                'welcome_buttons' => $settings->welcome_buttons ?? [],
            ],
        ]);
    }
}

