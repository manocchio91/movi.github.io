<?php

namespace App\Jobs;

use App\Models\Campaign;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class UpdateCampaignStatistics implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $campaignId) {}

    public function handle(): void
    {
        $campaign = Campaign::query()->findOrFail($this->campaignId);
        $views = DB::table('analytics_events')->where('campaign_id', $campaign->id)->where('type', 'campaign_view')->count();
        $clicks = DB::table('analytics_events')->where('campaign_id', $campaign->id)->where('type', 'campaign_click')->count();
        $bookings = DB::table('analytics_events')->where('campaign_id', $campaign->id)->where('type', 'campaign_booking')->count();

        $campaign->statistics()->updateOrCreate([], [
            'views' => $views,
            'clicks' => $clicks,
            'bookings_generated' => $bookings,
            'conversion_rate' => $clicks > 0 ? round(($bookings / $clicks) * 100, 2) : 0,
            'calculated_at' => now(),
        ]);
    }
}
