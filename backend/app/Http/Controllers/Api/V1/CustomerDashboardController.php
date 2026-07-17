<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventSuccessScoreService;
use Illuminate\Http\JsonResponse;

class CustomerDashboardController extends Controller
{
    public function show(EventSuccessScoreService $scores): JsonResponse
    {
        $user = request()->user();
        $preferredCategoryIds = $user->preferredCategories()->pluck('categories.id');
        $successfulEvents = Event::query()
            ->with(['categories', 'municipality.province', 'organizerProfile:id,ulid,name,type', 'successScore'])
            ->where('status', EventStatus::Completed->value)
            ->where('show_in_inspiration_feed', true)
            ->whereHas('successScore')
            ->when($preferredCategoryIds->isNotEmpty(), fn ($query) => $query->whereHas('categories', fn ($category) => $category->whereIn('categories.id', $preferredCategoryIds)))
            ->limit(6)
            ->get();
        $successfulEvents->each(fn (Event $event) => $event->successScore ?: $scores->calculate($event));

        return response()->json([
            'data' => [
                'successful_events' => $successfulEvents
                    ->sortByDesc(fn (Event $event) => $event->successScore?->success_score ?: 0)
                    ->values()
                    ->map(fn (Event $event) => [
                        'ulid' => $event->ulid,
                        'title' => $event->title,
                        'category' => $event->categories->first()?->name,
                        'municipality' => $event->municipality?->name,
                        'average_rating' => (float) ($event->successScore?->average_rating ?: 0),
                        'fill_rate' => (float) ($event->successScore?->fill_rate ?: 0),
                        'success_score' => (float) ($event->successScore?->success_score ?: 0),
                    ]),
                'upcoming_events' => Event::query()
                    ->with(['categories', 'municipality', 'organizerProfile'])
                    ->where('status', EventStatus::Published)
                    ->where('starts_at', '>', now())
                    ->orderBy('starts_at')
                    ->limit(6)
                    ->get(),
                'recommended_events' => Event::query()
                    ->with(['categories', 'municipality', 'organizerProfile'])
                    ->where('status', EventStatus::Published)
                    ->orderBy('starts_at')
                    ->limit(6)
                    ->get(),
                'bookings_count' => $user->bookings()->count(),
                'favorites_count' => $user->favorites()->count(),
                'notifications_unread' => $user->notifications()->where('read', false)->count(),
                'preferences' => $user->preferredCategories()->get(),
            ],
        ]);
    }
}
