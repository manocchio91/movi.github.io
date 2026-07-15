<?php

namespace App\Jobs;

use App\Models\OrganizerProfile;
use App\Services\OrganizerCalendarAvailabilityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class RecalculateOpportunitySnapshot implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $organizerProfileId, public array $filters) {}

    public function handle(OrganizerCalendarAvailabilityService $availability): void
    {
        $profile = OrganizerProfile::query()->find($this->organizerProfileId);

        if (! $profile) {
            return;
        }

        foreach ($availability->availability($profile, $this->filters) as $day) {
            DB::table('event_opportunity_snapshots')->updateOrInsert(
                [
                    'organizer_profile_id' => $profile->id,
                    'date' => $day['date'],
                    'municipality_id' => $this->filters['municipality_id'] ?? null,
                    'province_id' => $this->filters['province_id'] ?? null,
                ],
                [
                    'events_count' => $day['events_count'],
                    'crowding_level' => $day['crowding_level'],
                    'public_events_summary' => json_encode($day['events']),
                    'calculated_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }
}
