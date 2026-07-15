<?php

namespace App\Actions\Events;

use App\Actions\Notifications\NotifyInterestedCustomers;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;

class ModerateEvent
{
    public function approve(Event $event, User $admin): Event
    {
        abort_unless($event->status === EventStatus::PendingReview, 422, 'Solo eventi in moderazione.');

        $event->forceFill([
            'status' => EventStatus::Published,
            'approved_at' => now(),
            'approved_by' => $admin->id,
            'published_at' => now(),
        ])->save();

        app(NotifyInterestedCustomers::class)->handle($event->refresh());

        return $event->refresh();
    }

    public function reject(Event $event, User $admin): Event
    {
        $event->forceFill([
            'status' => EventStatus::Rejected,
            'approved_by' => $admin->id,
        ])->save();

        return $event->refresh();
    }

    public function suspend(Event $event, User $admin): Event
    {
        $event->forceFill([
            'status' => EventStatus::Suspended,
            'approved_by' => $admin->id,
        ])->save();

        return $event->refresh();
    }
}
