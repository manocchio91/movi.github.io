<?php

namespace App\Jobs;

use App\Actions\Notifications\NotifyInterestedCustomers;
use App\Models\Event;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyInterestedCustomersJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $eventId) {}

    public function handle(NotifyInterestedCustomers $notify): void
    {
        $event = Event::query()->find($this->eventId);

        if ($event !== null) {
            $notify->handle($event);
        }
    }
}
