<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuResource;
use App\Models\Activity;
use App\Services\Analytics\AnalyticsRecorder;
use Illuminate\Http\JsonResponse;

class CustomerMenuController extends Controller
{
    public function show(Activity $activity, AnalyticsRecorder $analytics): JsonResponse
    {
        $menu = $activity->menus()->where('status', 'published')->where('active', true)->with(['sections.items' => fn ($q) => $q->where('active', true)])->firstOrFail();
        $analytics->recordActivity('menu_viewed', $activity, request()->user());

        return response()->json(['data' => MenuResource::make($menu)]);
    }
}
