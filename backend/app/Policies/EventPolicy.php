<?php

namespace App\Policies;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function viewAnyOrganizer(User $user): bool
    {
        return $user->isOrganizer() && $user->organizerProfile()->where('verification_status', 'verified')->exists();
    }

    public function update(User $user, Event $event): bool
    {
        return $event->organizerProfile?->owner_user_id === $user->id
            && in_array($event->status, [EventStatus::Draft, EventStatus::Rejected, EventStatus::PendingReview, EventStatus::Published], true)
            && $event->starts_at->isFuture();
    }

    public function viewOrganizer(User $user, Event $event): bool
    {
        return $event->organizerProfile?->owner_user_id === $user->id;
    }

    public function submit(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }

    public function moderate(User $user): bool
    {
        return $user->isAdmin();
    }
}
