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

class TransitionReservation
{
    public function __construct(private readonly AnalyticsRecorder $analytics) {}

    public function handle(User $operator, Reservation $reservation, ReservationStatus $target): Reservation
    {
        return DB::transaction(function () use ($operator, $reservation, $target): Reservation {
            $locked = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($target, [ReservationStatus::Completed, ReservationStatus::NoShow], true), 422, 'Transizione non consentita.');
            abort_unless($locked->status === ReservationStatus::Confirmed, 422, 'La prenotazione non e confermata.');
            $endsAt = $locked->starts_at->copy()->addMinutes((int) $locked->service()->value('indicative_duration_minutes'));
            abort_if($endsAt->isFuture(), 422, 'Presenza registrabile solo dopo la conclusione del servizio.');
            $locked->forceFill(['status' => $target])->save();
            $kind = $target === ReservationStatus::Completed ? 'reservation_completed' : 'reservation_no_show';
            $notification = Notification::query()->create([
                'ulid' => (string) Str::ulid(), 'user_id' => $locked->user_id, 'activity_id' => $locked->activity_id, 'reservation_id' => $locked->id,
                'kind' => $kind, 'channel' => 'internal', 'template' => $kind, 'title' => $target === ReservationStatus::Completed ? 'Esperienza completata' : 'Mancata presenza registrata',
                'message' => $target === ReservationStatus::Completed ? 'Puoi ora lasciare una recensione verificata.' : 'Il gestore ha registrato la mancata presenza.',
                'status' => 'sent', 'read' => false, 'payload' => ['reservation_ulid' => $locked->ulid], 'deduplication_key' => "reservation:{$locked->id}:{$target->value}:internal", 'sent_at' => now(),
            ]);
            NotificationDelivery::query()->create(['notification_id' => $notification->id, 'status' => 'sent', 'channel' => 'internal', 'recipient' => (string) $locked->user_id, 'sent_at' => now()]);
            $this->analytics->recordActivity("reservation_{$target->value}", $locked->activity, $operator, $locked, "reservation_{$target->value}:{$locked->id}");

            return $locked->refresh()->load(['activity.municipality', 'service', 'user']);
        });
    }
}
