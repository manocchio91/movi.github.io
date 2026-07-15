<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Models\OrganizerProfile;
use App\Models\OrganizerRanking;
use Illuminate\Support\Collection;

class OrganizerRankingService
{
    public function rankOrganizers(string $period = 'all'): Collection
    {
        return OrganizerProfile::query()
            ->with(['owner', 'municipality', 'reviewStatistic'])
            ->withCount(['events', 'followers'])
            ->get()
            ->map(fn (OrganizerProfile $organizer) => $this->calculate($organizer, $period)->load('organizerProfile'))
            ->sortByDesc(fn (OrganizerRanking $ranking) => ($ranking->events_created * 1000) + ($ranking->average_rating * 100) + $ranking->average_fill_rate + ($ranking->total_participants / 100))
            ->values();
    }

    public function calculate(OrganizerProfile $organizer, string $period = 'all'): OrganizerRanking
    {
        $events = $organizer->events()->with(['bookings', 'reviewStatistic'])->get();
        $eventsCreated = $events->count();
        $eventsCompleted = $events->where('status', EventStatus::Completed)->count();
        $eventsCancelled = $events->where('status', EventStatus::Cancelled)->count();
        $soldOutEvents = $events->where('available_total', 0)->count();
        $totalParticipants = $events->flatMap->bookings->whereIn('status', ['confirmed', 'checked_in', 'completed'])->sum('total_people');

        $averageFill = $eventsCreated > 0
            ? round($events->avg(fn ($event) => $event->capacity_total > 0 ? (($event->capacity_total - $event->available_total) / $event->capacity_total) * 100 : 0), 2)
            : 0;

        $reviewStats = $events->pluck('reviewStatistic')->filter();
        $averageRating = $reviewStats->sum('reviews_count') > 0
            ? round($reviewStats->sum(fn ($stats) => $stats->average_rating * $stats->reviews_count) / $reviewStats->sum('reviews_count'), 2)
            : 0;

        return OrganizerRanking::query()->updateOrCreate(
            ['organizer_profile_id' => $organizer->id, 'ranking_period' => $period],
            [
                'events_created' => $eventsCreated,
                'events_completed' => $eventsCompleted,
                'events_cancelled' => $eventsCancelled,
                'sold_out_events' => $soldOutEvents,
                'average_fill_rate' => $averageFill,
                'average_rating' => $averageRating,
                'total_participants' => $totalParticipants,
                'follower_count' => $organizer->followers()->count(),
                'calculated_at' => now(),
            ]
        );
    }
}
