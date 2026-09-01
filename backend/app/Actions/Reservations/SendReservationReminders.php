<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationStatus;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\Reservation;
use Illuminate\Support\Str;

class SendReservationReminders
{
    public function handle(?int $hours = null): int
    {
        $hours ??= (int) config('reservations.reminder_hours', 24);
        $sent = 0;
        Reservation::query()->where('status', ReservationStatus::Confirmed)
            ->whereBetween('starts_at', [now()->addHours($hours)->subMinutes(5), now()->addHours($hours)->addMinutes(5)])
            ->each(function (Reservation $reservation) use (&$sent): void {
                $key = "reservation:{$reservation->id}:reminder";
                if (Notification::query()->where('deduplication_key', $key)->exists()) {
                    return;
                }
                $notification = Notification::query()->create([
                    'ulid' => (string) Str::ulid(), 'user_id' => $reservation->user_id, 'activity_id' => $reservation->activity_id, 'reservation_id' => $reservation->id,
                    'kind' => 'reservation_reminder', 'channel' => 'internal', 'template' => 'reservation_reminder', 'title' => 'Prenotazione in arrivo',
                    'message' => 'La tua prenotazione e imminente.', 'status' => 'sent', 'read' => false, 'payload' => ['reservation_ulid' => $reservation->ulid, 'starts_at' => $reservation->starts_at->toIso8601String()],
                    'deduplication_key' => $key, 'sent_at' => now(),
                ]);
                NotificationDelivery::query()->create(['notification_id' => $notification->id, 'status' => 'sent', 'channel' => 'internal', 'recipient' => (string) $reservation->user_id, 'sent_at' => now()]);
                $sent++;
            });

        return $sent;
    }
}
