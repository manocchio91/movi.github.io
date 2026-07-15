<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Review;
use Illuminate\Http\JsonResponse;

class OrganizerReviewController extends Controller
{
    public function index(): JsonResponse
    {
        $profile = request()->user()->organizerProfile;

        abort_unless($profile, 403, 'Profilo organizzatore richiesto.');

        $reviews = Review::query()
            ->with(['event:id,ulid,title,starts_at', 'user:id,name', 'images'])
            ->where('organizer_profile_id', $profile->id)
            ->where('status', 'approved')
            ->whereNull('hidden_at');

        return response()->json([
            'statistics' => $profile->reviewStatistic,
            'star_distribution' => [
                '1' => (int) ($profile->reviewStatistic?->one_star ?? 0),
                '2' => (int) ($profile->reviewStatistic?->two_star ?? 0),
                '3' => (int) ($profile->reviewStatistic?->three_star ?? 0),
                '4' => (int) ($profile->reviewStatistic?->four_star ?? 0),
                '5' => (int) ($profile->reviewStatistic?->five_star ?? 0),
            ],
            'recent' => (clone $reviews)->latest('reviewed_at')->limit(8)->get(),
            'best' => (clone $reviews)->where('rating', '>=', 4)->orderByDesc('rating')->latest('reviewed_at')->limit(8)->get(),
            'critical' => (clone $reviews)->where('rating', '<=', 3)->orderBy('rating')->latest('reviewed_at')->limit(8)->get(),
        ]);
    }

    public function events(): JsonResponse
    {
        $profile = request()->user()->organizerProfile;
        abort_unless($profile, 403, 'Profilo organizzatore richiesto.');

        $events = $profile->events()
            ->with(['categories', 'reviewStatistic', 'reviews' => fn ($query) => $query->where('status', 'approved')->whereNull('hidden_at')->latest('reviewed_at')->limit(1)])
            ->whereHas('reviews', fn ($query) => $query->where('status', 'approved')->whereNull('hidden_at'))
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (Event $event) => [
                'event' => $event,
                'statistics' => $event->reviewStatistic,
                'star_distribution' => [
                    '1' => (int) ($event->reviewStatistic?->one_star ?? 0),
                    '2' => (int) ($event->reviewStatistic?->two_star ?? 0),
                    '3' => (int) ($event->reviewStatistic?->three_star ?? 0),
                    '4' => (int) ($event->reviewStatistic?->four_star ?? 0),
                    '5' => (int) ($event->reviewStatistic?->five_star ?? 0),
                ],
                'latest_review' => $event->reviews->first(),
            ]);

        return response()->json(['data' => $events]);
    }

    public function event(Event $event): JsonResponse
    {
        $this->authorize('viewOrganizer', $event);

        return response()->json([
            'event' => $event->load('categories'),
            'statistics' => $event->reviewStatistic,
            'data' => $event->reviews()
                ->with(['user:id,name', 'images'])
                ->where('status', 'approved')
                ->whereNull('hidden_at')
                ->latest('reviewed_at')
                ->get(),
        ]);
    }
}
