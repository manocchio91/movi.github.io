<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\BusinessIntelligence\AdminBusinessMetrics;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function show(AdminBusinessMetrics $metrics): JsonResponse
    {
        return response()->json(['data' => $metrics->calculate()]);
    }
}
