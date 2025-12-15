<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionCheckController extends Controller
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Проверка статуса подписки
     */
    public function check(Request $request): JsonResponse
    {
        $subscriptionData = $this->subscriptionService->getSubscriptionData();
        
        if (!$subscriptionData) {
            return response()->json([
                'success' => false,
                'message' => 'Не удалось получить данные подписки',
            ], 500);
        }

        $daysLeft = $this->subscriptionService->getDaysUntilExpiry();
        $isExpiringSoon = $this->subscriptionService->isExpiringSoon(3);
        $isActive = $this->subscriptionService->isActive();

        return response()->json([
            'success' => true,
            'subscription' => $subscriptionData,
            'is_active' => $isActive,
            'is_expiring_soon' => $isExpiringSoon,
            'days_until_expiry' => $daysLeft,
        ]);
    }
}

