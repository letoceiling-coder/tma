<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ChannelController extends Controller
{
    /**
     * Получить список каналов
     */
    public function index(): JsonResponse
    {
        $channels = Channel::orderBy('priority', 'desc')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $channels,
        ]);
    }

    /**
     * Создать новый канал
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:channels,username',
            'title' => 'required|string|max:255',
            'external_url' => [
                'nullable',
                'string',
                'max:500',
                'url',
                function ($attribute, $value, $fail) {
                    if ($value && !str_starts_with($value, 'https://t.me/')) {
                        $fail('Внешняя ссылка должна начинаться с https://t.me/');
                    }
                },
            ],
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $channel = Channel::create([
            'username' => $request->username,
            'title' => $request->title,
            'external_url' => $request->external_url,
            'priority' => $request->priority ?? 0,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'data' => $channel,
            'message' => 'Канал успешно создан',
        ], 201);
    }

    /**
     * Обновить канал
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $channel = Channel::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:channels,username,' . $id,
            'title' => 'required|string|max:255',
            'external_url' => [
                'nullable',
                'string',
                'max:500',
                'url',
                function ($attribute, $value, $fail) {
                    if ($value && !str_starts_with($value, 'https://t.me/')) {
                        $fail('Внешняя ссылка должна начинаться с https://t.me/');
                    }
                },
            ],
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors(),
            ], 422);
        }

        $channel->update([
            'username' => $request->username,
            'title' => $request->title,
            'external_url' => $request->has('external_url') ? $request->external_url : $channel->external_url,
            'priority' => $request->priority ?? $channel->priority,
            'is_active' => $request->is_active ?? $channel->is_active,
        ]);

        return response()->json([
            'data' => $channel,
            'message' => 'Канал успешно обновлен',
        ]);
    }

    /**
     * Удалить канал
     */
    public function destroy(int $id): JsonResponse
    {
        $channel = Channel::findOrFail($id);
        $channel->delete();

        return response()->json([
            'message' => 'Канал успешно удален',
        ]);
    }
}

