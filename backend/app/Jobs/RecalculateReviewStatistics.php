<?php

namespace App\Jobs;

use App\Models\EventStatistic;
use App\Models\Review;
use App\Models\ReviewStatistic;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalculateReviewStatistics implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $eventId, public int $organizerProfileId) {}

    public function handle(): void
    {
        $eventStatistic = $this->calculate(['event_id' => $this->eventId]);
        $this->calculate(['organizer_profile_id' => $this->organizerProfileId]);

        EventStatistic::query()->updateOrCreate(
            ['event_id' => $this->eventId],
            ['average_rating' => $eventStatistic->average_rating, 'calculated_at' => now()],
        );
    }

    private function calculate(array $scope): ReviewStatistic
    {
        $query = Review::query()->where($scope)->where('status', 'approved')->whereNull('hidden_at');
        $reviews = $query->get();
        $count = $reviews->count();

        return ReviewStatistic::query()->updateOrCreate($scope, [
            'reviews_count' => $count,
            'average_rating' => round((float) $reviews->avg('rating'), 2),
            'average_quality' => round((float) $reviews->avg('experience_quality_rating'), 2),
            'average_organization' => round((float) $reviews->avg('organization_rating'), 2),
            'average_location' => round((float) $reviews->avg('location_rating'), 2),
            'average_value' => round((float) $reviews->avg('value_rating'), 2),
            'average_schedule_respect' => round((float) $reviews->avg('schedule_respect_rating'), 2),
            'average_staff' => round((float) $reviews->avg('staff_rating'), 2),
            'one_star' => $reviews->where('rating', 1)->count(),
            'two_star' => $reviews->where('rating', 2)->count(),
            'three_star' => $reviews->where('rating', 3)->count(),
            'four_star' => $reviews->where('rating', 4)->count(),
            'five_star' => $reviews->where('rating', 5)->count(),
            'satisfied_rate' => $count > 0 ? round(($reviews->where('rating', '>=', 4)->count() / $count) * 100, 2) : 0,
            'recommendation_rate' => $count > 0 ? round(($reviews->where('would_recommend', true)->count() / $count) * 100, 2) : 0,
            'calculated_at' => now(),
        ]);
    }
}
