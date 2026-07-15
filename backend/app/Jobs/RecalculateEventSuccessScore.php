<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\EventSuccessScoreService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalculateEventSuccessScore implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $eventId) {}

    public function handle(EventSuccessScoreService $scores): void
    {
        $event = Event::query()->find($this->eventId);

        if ($event) {
            $scores->calculate($event);
        }
    }
}
