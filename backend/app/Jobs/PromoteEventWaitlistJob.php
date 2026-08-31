<?php

namespace App\Jobs;

use App\Actions\Waitlist\PromoteEventWaitlist;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PromoteEventWaitlistJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $eventId) {}

    public function handle(PromoteEventWaitlist $promote): void
    {
        $promote->handle($this->eventId);
    }
}
