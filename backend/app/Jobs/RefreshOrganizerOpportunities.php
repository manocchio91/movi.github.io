<?php

namespace App\Jobs;

use App\Models\OrganizerProfile;
use App\Services\OrganizerOpportunityEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshOrganizerOpportunities implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $organizerProfileId, public array $input) {}

    public function handle(OrganizerOpportunityEngine $engine): void
    {
        $profile = OrganizerProfile::query()->findOrFail($this->organizerProfileId);
        $engine->analyze($profile, $this->input);
    }
}
