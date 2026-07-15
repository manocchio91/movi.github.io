<?php

namespace App\Jobs;

use App\Models\DashboardCache;
use App\Models\OrganizerProfile;
use App\Services\BusinessIntelligence\OrganizerDashboardMetrics;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalculateOrganizerDashboard implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $organizerProfileId) {}

    public function handle(OrganizerDashboardMetrics $metrics): void
    {
        $profile = OrganizerProfile::query()->findOrFail($this->organizerProfileId);

        DashboardCache::query()->updateOrCreate(
            ['organizer_profile_id' => $profile->id, 'cache_key' => 'dashboard'],
            [
                'payload' => [
                    'metrics' => $metrics->calculate($profile),
                    'categories' => $metrics->categoryPerformance($profile),
                ],
                'calculated_at' => now(),
                'expires_at' => now()->addHour(),
            ],
        );
    }
}
