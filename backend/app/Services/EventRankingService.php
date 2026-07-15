<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventRanking;
use Illuminate\Support\Collection;

class EventRankingService
{
    public function rankEvents(string $period = 'all'): Collection
    {
        return Event::query()
            ->with(['organizerProfile', 'categories', 'reviewStatistic'])
            ->withCount(['bookings', 'reviews'])
            ->whereIn('status', [EventStatus::Published->value, EventStatus::SoldOut->value, EventStatus::Completed->value])
            ->get()
            ->map(fn (Event $event) => $this->calculate($event, $period)->load(['event.organizerProfile', 'event.categories']))
            ->sortByDesc('final_score')
            ->values();
    }

    public function breakdown(Event $event): array
    {
        $ranking = $this->calculate($event);

        return [
            'event' => $event->load(['organizerProfile', 'categories', 'reviewStatistic']),
            'ranking' => $ranking,
            'formula' => [
                'creativity_score' => '30%: rating admin se presente, altrimenti completezza contenuti e categorie.',
                'participation_score' => '35%: tasso di riempimento e partecipazione effettiva.',
                'review_score' => '35%: media recensioni, affidabilita del campione e raccomandazioni.',
            ],
        ];
    }

    public function calculate(Event $event, string $period = 'all'): EventRanking
    {
        $event->loadMissing(['reviewStatistic']);

        $creativity = $this->creativityScore($event);
        $participation = $this->participationScore($event);
        $review = $this->reviewScore($event);
        $final = round(($creativity * 0.30) + ($participation * 0.35) + ($review * 0.35), 2);

        return EventRanking::query()->updateOrCreate(
            ['event_id' => $event->id, 'ranking_period' => $period],
            [
                'creativity_score' => $creativity,
                'participation_score' => $participation,
                'review_score' => $review,
                'final_score' => $final,
                'calculated_at' => now(),
                'formula_version' => 'post-moderation-v1',
            ]
        );
    }

    private function creativityScore(Event $event): float
    {
        if ($event->admin_creativity_rating) {
            return round(min(100, max(0, ($event->admin_creativity_rating / 5) * 100)), 2);
        }

        $score = 20;
        $score += filled($event->program) ? 20 : 0;
        $score += filled($event->included) ? 10 : 0;
        $score += filled($event->important_information) ? 10 : 0;
        $score += strlen((string) $event->description) >= 500 ? 20 : 10;
        $score += $event->categories()->count() > 1 ? 10 : 0;
        $score += $event->family_friendly ? 10 : 0;

        return round(min(100, $score), 2);
    }

    private function participationScore(Event $event): float
    {
        if ($event->capacity_total === 0 || $event->status === EventStatus::Cancelled) {
            return 0.0;
        }

        $booked = $event->bookings()->whereIn('status', ['confirmed', 'checked_in', 'completed'])->sum('total_people');
        $attended = $event->bookings()->whereIn('status', ['checked_in', 'completed'])->sum('total_people');
        $fillRate = min(1, $booked / $event->capacity_total);
        $attendanceRate = $booked > 0 ? min(1, $attended / $booked) : 0.75;

        return round((($fillRate * 0.75) + ($attendanceRate * 0.25)) * 100, 2);
    }

    private function reviewScore(Event $event): float
    {
        $stats = $event->reviewStatistic;
        if (! $stats || $stats->reviews_count === 0) {
            return 0.0;
        }

        $rating = ((float) $stats->average_rating / 5) * 100;
        $reliability = min(1, $stats->reviews_count / 10);
        $recommendation = (float) ($stats->recommendation_rate ?? 0);

        return round(($rating * 0.70 * $reliability) + ($recommendation * 0.30), 2);
    }
}
