<?php

namespace App\Actions\Bookings;

use App\Enums\EventStatus;
use App\Jobs\PromoteEventWaitlistJob;
use App\Models\Booking;
use App\Models\Event;
use App\Services\Analytics\AnalyticsRecorder;
use Illuminate\Support\Facades\DB;

class CancelBooking
{
    public function handle(Booking $booking, string $status = 'cancelled_by_customer'): Booking
    {
        return DB::transaction(function () use ($booking, $status): Booking {
            /** @var Booking $lockedBooking */
            $lockedBooking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedBooking->status === 'confirmed', 422, 'Prenotazione non annullabile.');

            /** @var Event $event */
            $event = Event::query()->whereKey($lockedBooking->event_id)->lockForUpdate()->firstOrFail();

            foreach ($lockedBooking->items()->with('ticketType')->get() as $item) {
                if ($item->ticketType) {
                    $item->ticketType->forceFill([
                        'booked_quantity' => max(0, $item->ticketType->booked_quantity - $item->quantity),
                    ])->save();
                }
            }

            $lockedBooking->forceFill([
                'status' => $status,
                'cancelled_at' => now(),
            ])->save();

            $event->increment('available_total', $lockedBooking->total_people);
            $event->refresh();

            if ($event->status === EventStatus::SoldOut && $event->starts_at->isFuture()) {
                $event->forceFill(['status' => EventStatus::Published])->save();
            }

            DB::afterCommit(fn () => PromoteEventWaitlistJob::dispatch($event->id));
            app(AnalyticsRecorder::class)->record('booking_cancelled', $event, $lockedBooking->user, "booking_cancelled:{$lockedBooking->id}");

            return $lockedBooking->refresh();
        });
    }
}
