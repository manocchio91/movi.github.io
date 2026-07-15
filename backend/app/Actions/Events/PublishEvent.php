<?php

namespace App\Actions\Events;

use App\Actions\Notifications\NotifyInterestedCustomers;
use App\Enums\EventStatus;
use App\Models\Event;

class PublishEvent
{
    public function handle(Event $event): Event
    {
        abort_unless($event->organizerProfile?->verification_status === 'verified', 403, 'Profilo organizzatore non approvato.');
        abort_unless(in_array($event->status, [EventStatus::Draft, EventStatus::PendingReview], true), 422, 'Evento non pubblicabile.');

        $event->forceFill([
            'status' => EventStatus::Published,
            'published_at' => now(),
        ])->save();

        app(NotifyInterestedCustomers::class)->handle($event->refresh());

        return $event->refresh();
    }
}
