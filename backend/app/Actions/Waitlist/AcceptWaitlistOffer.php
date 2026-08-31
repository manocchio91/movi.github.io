<?php

namespace App\Actions\Waitlist;

use App\Actions\Bookings\CreateBooking;
use App\Enums\EventStatus;
use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\DB;

class AcceptWaitlistOffer
{
    public function __construct(private readonly CreateBooking $createBooking) {}

    public function handle(User $user, Event $event, array $data): Booking
    {
        return DB::transaction(function () use ($user, $event, $data): Booking {
            $entry = WaitlistEntry::query()->where('event_id', $event->id)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            if ($entry->status === 'accepted' && $entry->booking_id !== null) {
                return Booking::query()->findOrFail($entry->booking_id)->load(['items.ticketType', 'event']);
            }

            abort_unless($entry->status === 'offered', 422, 'Nessuna offerta attiva.');
            abort_if($entry->reserved_until === null || $entry->reserved_until->isPast(), 422, 'Offerta scaduta.');

            $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $lockedEvent->increment('available_total', $entry->requested_places);
            if ($lockedEvent->status === EventStatus::SoldOut) {
                $lockedEvent->forceFill(['status' => EventStatus::Published])->save();
            }

            $payload = [...$entry->booking_payload, ...$data];
            $booking = $this->createBooking->handle($user, $lockedEvent->refresh(), $payload, "waitlist:{$entry->ulid}");
            $entry->forceFill([
                'status' => 'accepted', 'accepted_at' => now(), 'reserved_until' => null, 'booking_id' => $booking->id,
            ])->save();

            return $booking;
        });
    }
}
