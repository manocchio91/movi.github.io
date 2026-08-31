<?php

namespace App\Actions\Waitlist;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\DB;

class LeaveEventWaitlist
{
    public function handle(User $user, Event $event): ?WaitlistEntry
    {
        return DB::transaction(function () use ($user, $event): ?WaitlistEntry {
            $entry = WaitlistEntry::query()->where('event_id', $event->id)->where('user_id', $user->id)->lockForUpdate()->first();
            if ($entry === null || in_array($entry->status, ['accepted', 'cancelled', 'expired'], true)) {
                return $entry;
            }

            if ($entry->status === 'offered') {
                $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
                $lockedEvent->increment('available_total', $entry->requested_places);
                $lockedEvent->refresh();
                if ($lockedEvent->status === EventStatus::SoldOut && $lockedEvent->starts_at->isFuture()) {
                    $lockedEvent->forceFill(['status' => EventStatus::Published])->save();
                }
            }

            $entry->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'reserved_until' => null])->save();

            return $entry->refresh()->load(['event', 'booking']);
        });
    }
}
