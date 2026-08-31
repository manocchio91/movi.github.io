<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizerFollowerResource;
use App\Models\AnalyticsEvent;
use App\Models\Campaign;
use App\Services\BusinessIntelligence\OrganizerDashboardMetrics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrganizerAnalyticsController extends Controller
{
    public function statistics(OrganizerDashboardMetrics $metrics): JsonResponse
    {
        $profile = request()->user()->organizerProfile;
        abort_unless($profile !== null, 403);

        return response()->json([
            'data' => $metrics->calculate($profile),
            'categories' => $metrics->categoryPerformance($profile),
        ]);
    }

    public function analytics(): JsonResponse
    {
        $profile = request()->user()->organizerProfile;
        abort_unless($profile !== null, 403);

        return response()->json([
            'data' => AnalyticsEvent::query()
                ->where('organizer_profile_id', $profile->id)
                ->latest('occurred_at')
                ->limit(100)
                ->get(),
        ]);
    }

    public function followers(): JsonResponse
    {
        $profile = request()->user()->organizerProfile;
        abort_unless($profile !== null, 403);

        return OrganizerFollowerResource::collection(
            $profile->followers()->latest('organizer_followers.created_at')->get(),
        )->response();
    }

    public function categories(OrganizerDashboardMetrics $metrics): JsonResponse
    {
        $profile = request()->user()->organizerProfile;
        abort_unless($profile !== null, 403);

        return response()->json(['data' => $metrics->categoryPerformance($profile)]);
    }

    public function campaigns(): JsonResponse
    {
        $profile = request()->user()->organizerProfile;
        abort_unless($profile !== null, 403);

        return response()->json([
            'data' => Campaign::query()
                ->where('organizer_profile_id', $profile->id)
                ->with('statistics')
                ->latest()
                ->get(),
        ]);
    }

    public function storeCampaign(Request $request): JsonResponse
    {
        $profile = $request->user()->organizerProfile;
        abort_unless($profile !== null, 403);

        $data = $request->validate([
            'event_id' => ['nullable', 'exists:events,id'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        $campaign = Campaign::query()->create([
            ...$data,
            'ulid' => (string) Str::ulid(),
            'organizer_profile_id' => $profile->id,
            'status' => 'active',
        ]);

        $campaign->statistics()->create([
            'views' => 0,
            'clicks' => 0,
            'bookings_generated' => 0,
            'conversion_rate' => 0,
            'calculated_at' => now(),
        ]);

        return response()->json(['data' => $campaign->load('statistics')], 201);
    }
}
