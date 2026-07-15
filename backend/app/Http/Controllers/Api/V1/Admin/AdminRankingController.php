<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventRankingService;
use App\Services\OrganizerRankingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRankingController extends Controller
{
    public function events(Request $request, EventRankingService $service): JsonResponse
    {
        $this->authorize('moderate', Event::class);

        return response()->json(['data' => $service->rankEvents($request->query('period', 'all'))]);
    }

    public function organizers(Request $request, OrganizerRankingService $service): JsonResponse
    {
        $this->authorize('moderate', Event::class);

        return response()->json(['data' => $service->rankOrganizers($request->query('period', 'all'))]);
    }

    public function eventBreakdown(Event $event, EventRankingService $service): JsonResponse
    {
        $this->authorize('moderate', Event::class);

        return response()->json(['data' => $service->breakdown($event)]);
    }
}
