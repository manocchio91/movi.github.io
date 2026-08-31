<?php

namespace App\Actions\Waitlist;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\DB;

class ExpireWaitlistOffers
{
    public function __construct(private readonly PromoteEventWaitlist $promote) {}

    public function handle(): int
    {
        $eventIds = WaitlistEntry::query()->where('status', 'offered')->where('reserved_until', '<=', now())
            ->pluck('event_id')->unique();
        $expired = 0;

        foreach ($eventIds as $eventId) {
            DB::transaction(function () use ($eventId, &$expired): void {
                $event = Event::query()->whereKey($eventId)->lockForUpdate()->first();
                if ($event === null) {
                    return;
                }
                $entries = WaitlistEntry::query()->where('event_id', $eventId)->where('status', 'offered')
                    ->where('reserved_until', '<=', now())->lockForUpdate()->get();
                foreach ($entries as $entry) {
                    $event->increment('available_total', $entry->requested_places);
                    $entry->forceFill(['status' => 'expired', 'expired_at' => now(), 'reserved_until' => null])->save();
                    $expired++;
                }
                $event->refresh();
                if ($event->status === EventStatus::SoldOut && $event->starts_at->isFuture()) {
                    $event->forceFill(['status' => EventStatus::Published])->save();
                }
            });
            $this->promote->handle((int) $eventId);
        }

        return $expired;
    }
}
