<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BusinessIntelligence\OrganizerDashboardMetrics;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrganizerDashboardController extends Controller
{
    public function show(OrganizerDashboardMetrics $metrics): JsonResponse
    {
        $profile = request()->user()->organizerProfile;
        abort_unless($profile !== null, 403);

        $eventIds = $profile->events()->pluck('id');
        $bi = $metrics->calculate($profile);

        return response()->json([
            'data' => [
                'subscription' => $profile->subscription?->load('plan.features'),
                'bi' => $bi,
                'category_performance' => $metrics->categoryPerformance($profile),
                'active_experiences' => $bi['future_events'],
                'future_experiences' => $bi['future_events'],
                'completed_experiences' => $bi['completed_events'],
                'unpublished_experiences' => $bi['unpublished_events'],
                'bookings_total' => $bi['bookings_received'],
                'available_places' => $bi['available_places'],
                'participants_total' => $bi['participants_total'],
                'views_total' => $bi['views_total'],
                'favorites_received' => DB::table('favorites')->whereIn('event_id', $eventIds)->count(),
                'followers' => $bi['followers_total'],
                'reviews' => DB::table('reviews')->whereIn('event_id', $eventIds)->count(),
            ],
        ]);
    }
}
