<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;

class SubscriptionPlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => SubscriptionPlan::query()
                ->where('is_public', true)
                ->where('is_active', true)
                ->with('features')
                ->orderBy('monthly_price_cents')
                ->get(),
        ]);
    }
}
