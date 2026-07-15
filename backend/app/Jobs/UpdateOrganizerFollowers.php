<?php

namespace App\Jobs;

use App\Jobs\RecalculateOrganizerKpis;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UpdateOrganizerFollowers implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $organizerProfileId) {}

    public function handle(): void
    {
        RecalculateOrganizerKpis::dispatch($this->organizerProfileId);
    }
}
