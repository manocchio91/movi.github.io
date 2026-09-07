<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationStatus;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Analytics\AnalyticsRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CancelReservation
{
    public function __construct(private readonly AnalyticsRecorder $analytics) {}

    public function handle(User $operator, Reservation $reservation, ?string $reason = null): Reservation
    {
        return DB::transaction(function () use ($operator, $reservation, $reason): Reservation {
            $locked = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === ReservationStatus::Cancelled) {
                return $locked->load(['activity', 'service']);
            }
            abort_unless($locked->status === ReservationStatus::Confirmed && $locked->starts_at->isFuture(), 422, 'Prenotazione non annullabile.');
            $locked->forceFill(['status' => ReservationStatus::Cancelled, 'cancelled_at' => now(), 'cancellation_reason' => $reason, 'cancelled_by_user_id' => $operator->id])->save();
            if ($locked->preorder()->where('status', 'confirmed')->update(['status' => 'cancelled'])) {
                $this->analytics->recordActivity('preorder_cancelled', $locked->activity, $locked->user, $locked);
            }
            $notification = Notification::query()->create([
                'ulid' => (string) Str::ulid(), 'user_id' => $locked->user_id, 'activity_id' => $locked->activity_id,
                'reservation_id' => $locked->id, 'kind' => 'reservation_cancelled', 'channel' => 'internal',
                'template' => 'reservation_cancelled', 'title' => 'Prenotazione annullata', 'message' => 'La prenotazione è stata annullata.',
                'status' => 'sent', 'read' => false, 'payload' => ['reservation_ulid' => $locked->ulid],
                'deduplication_key' => "reservation:{$locked->id}:cancelled:internal", 'sent_at' => now(),
            ]);
            NotificationDelivery::query()->create(['notification_id' => $notification->id, 'status' => 'sent', 'channel' => 'internal', 'recipient' => (string) $locked->user_id, 'sent_at' => now()]);
            $this->analytics->recordActivity('reservation_cancelled', $locked->activity, $locked->user, $locked, "reservation_cancelled:{$locked->id}");

            return $locked->refresh()->load(['activity', 'service']);
        });
    }
}
