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
        $validator = Validator::make($request->all(), [
            'welcome_text' => 'nullable|string|max:4096',
            'welcome_banner_url' => 'nullable|string|max:500|url',
            'welcome_buttons' => 'nullable|array|max:5',
            'welcome_buttons.*.label' => 'required_with:welcome_buttons|string|max:64',
            'welcome_buttons.*.url' => 'required_with:welcome_buttons|string|max:500|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updateData = [];
        
        if ($request->has('welcome_text')) {
            $updateData['welcome_text'] = $request->welcome_text;
        }
        
        if ($request->has('welcome_banner_url')) {
            $updateData['welcome_banner_url'] = $request->welcome_banner_url ?: null;
        }
        
        if ($request->has('welcome_buttons')) {
            // Валидация и нормализация кнопок
            $buttons = $request->welcome_buttons;
            if (is_array($buttons) && !empty($buttons)) {
                // Фильтруем пустые кнопки
                $buttons = array_filter($buttons, function($button) {
                    return !empty($button['label']) && !empty($button['url']);
                });
                // Переиндексируем массив
                $buttons = array_values($buttons);
            } else {
                $buttons = null;
            }
            $updateData['welcome_buttons'] = $buttons;
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

