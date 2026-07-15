<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Services\BusinessIntelligence\OrganizerDashboardMetrics;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrganizerDashboardController extends Controller
{
    public function show(OrganizerDashboardMetrics $metrics): JsonResponse
    {
        $profile = request()->user()->organizerProfile;
        abort_unless($profile, 403);

        $events = $profile->events();
        $eventIds = $profile->events()->pluck('id');

        return response()->json([
            'data' => [
                'subscription' => $profile->subscription?->load('plan.features'),
                'bi' => $metrics->calculate($profile),
                'category_performance' => $metrics->categoryPerformance($profile),
                'active_experiences' => (clone $events)->whereIn('status', [EventStatus::Published->value, EventStatus::SoldOut->value])->count(),
                'future_experiences' => (clone $events)->where('starts_at', '>=', now())->count(),
                'completed_experiences' => (clone $events)->where('status', EventStatus::Completed->value)->count(),
                'bookings_total' => $profile->events()->withCount('bookings')->get()->sum('bookings_count'),
                'available_places' => (clone $events)->sum('available_total'),
                'participants_total' => $profile->events()->withSum('bookings', 'total_people')->get()->sum('bookings_sum_total_people'),
                'views_total' => $profile->events()->withCount('views')->get()->sum('views_count'),
                'favorites_received' => DB::table('favorites')->whereIn('event_id', $eventIds)->count(),
                'followers' => $profile->followers()->count(),
                'reviews' => DB::table('reviews')->whereIn('event_id', $eventIds)->count(),
            ],
        ]);
    }
}
