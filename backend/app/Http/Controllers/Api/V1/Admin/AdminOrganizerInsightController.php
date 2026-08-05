<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrganizerProfile;
use Illuminate\Http\JsonResponse;

class AdminOrganizerInsightController extends Controller
{
    public function show(OrganizerProfile $organizer): JsonResponse
    {
        $this->authorize('moderate', OrganizerProfile::class);

        $events = $organizer->events();

        return response()->json([
            'data' => $organizer->load(['owner', 'municipality', 'ranking', 'subscription.plan', 'subscription.history'])->loadCount(['events', 'reviews']),
            'overview' => [
                'events_total' => (clone $events)->count(),
                'events_published' => (clone $events)->where('status', 'published')->count(),
                'events_cancelled' => (clone $events)->where('status', 'cancelled')->count(),
                'bookings_total' => $organizer->events()->withCount('bookings')->get()->sum('bookings_count'),
                'reviews_total' => $organizer->events()->withCount('reviews')->get()->sum('reviews_count'),
            ],
        ]);
    }

    public function events(OrganizerProfile $organizer): JsonResponse
    {
        $this->authorize('moderate', OrganizerProfile::class);

        return response()->json([
            'data' => $organizer->events()
                ->with(['categories', 'municipality', 'reviewStatistic'])
                ->withCount(['bookings', 'reviews'])
                ->latest('starts_at')
                ->get(),
        ]);
    }

    public function reviews(OrganizerProfile $organizer): JsonResponse
    {
        $this->authorize('moderate', OrganizerProfile::class);

        return response()->json([
            'data' => $organizer->reviews()
                ->with(['event:id,ulid,title', 'user:id,name'])
                ->latest('reviewed_at')
                ->get(),
        ]);
    }

    public function bookings(OrganizerProfile $organizer): JsonResponse
    {
        $this->authorize('moderate', OrganizerProfile::class);

        return response()->json([
            'data' => $organizer->events()
                ->withCount([
                    'bookings',
                    'bookings as confirmed_bookings_count' => fn ($query) => $query->where('status', 'confirmed'),
                    'bookings as cancelled_bookings_count' => fn ($query) => $query->whereIn('status', ['cancelled_by_customer', 'cancelled_by_organizer']),
                ])
                ->latest('starts_at')
                ->get(['id', 'ulid', 'title', 'starts_at', 'status', 'capacity_total', 'available_total']),
        ]);
    }

    public function cancellations(OrganizerProfile $organizer): JsonResponse
    {
        $this->authorize('moderate', OrganizerProfile::class);

        return response()->json([
            'data' => $organizer->events()
                ->where('status', 'cancelled')
                ->with(['cancellation.cancelledBy:id,name', 'bookings' => fn ($query) => $query->where('status', 'cancelled_by_organizer')])
                ->latest('starts_at')
                ->get(),
        ]);
    }
}
