<?php

namespace App\Actions\Events;

use App\Enums\EventStatus;
use App\Models\Event;

class SubmitEventForReview
{
    public function handle(Event $event): Event
    {
        abort_unless(in_array($event->status, [EventStatus::Draft, EventStatus::Rejected], true), 422, 'Evento non inviabile.');

        $event->forceFill([
            'status' => EventStatus::PendingReview,
            'submitted_at' => now(),
        ])->save();

        return $event->refresh();
    }
}
