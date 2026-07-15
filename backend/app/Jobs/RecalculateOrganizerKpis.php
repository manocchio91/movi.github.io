<?php

namespace App\Jobs;

use App\Models\OrganizerStatistic;
use App\Models\OrganizerProfile;
use App\Services\BusinessIntelligence\OrganizerDashboardMetrics;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalculateOrganizerKpis implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $organizerProfileId) {}

    public function handle(OrganizerDashboardMetrics $metrics): void
    {
        $profile = OrganizerProfile::query()->findOrFail($this->organizerProfileId);
        $data = $metrics->calculate($profile);

        OrganizerStatistic::query()->updateOrCreate(
            [
                'organizer_profile_id' => $profile->id,
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
            ],
            [
                'published_events' => $data['published_events'],
                'future_events' => $data['future_events'],
                'completed_events' => $data['completed_events'],
                'cancelled_events' => $data['cancelled_events'],
                'sold_out_events' => $data['sold_out_events'],
                'bookings_count' => $data['bookings_received'],
                'participants_total' => $data['participants_total'],
                'unique_participants' => $data['unique_participants'],
                'returning_participants' => $data['returning_participants'],
                'returning_rate' => $data['returning_rate'],
                'average_rating' => $data['average_rating'],
                'followers_total' => $data['followers_total'],
                'new_followers' => $data['new_followers'],
                'calculated_at' => now(),
            ],
        );
    }
}
