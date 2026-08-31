<?php

namespace App\Actions\Reservations;

use App\Enums\ActivityStatus;
use App\Enums\ReservationStatus;
use App\Models\Activity;
use App\Models\ActivityService;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Analytics\AnalyticsRecorder;
use App\Services\Reservations\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateReservation
{
    public function __construct(private readonly AvailabilityService $availability, private readonly AnalyticsRecorder $analytics) {}

    public function handle(User $user, Activity $activity, ActivityService $service, array $data, string $idempotencyKey): Reservation
    {
        return DB::transaction(function () use ($user, $activity, $service, $data, $idempotencyKey): Reservation {
            $lockedService = ActivityService::query()->whereKey($service->id)->lockForUpdate()->firstOrFail();
            $existing = Reservation::query()->where('user_id', $user->id)->where('activity_id', $activity->id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $this->response($existing);
            }

            $lockedActivity = Activity::query()->whereKey($activity->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedActivity->status === ActivityStatus::Published && $lockedActivity->booking_enabled, 422, 'Prenotazioni non disponibili.');
            abort_unless($lockedService->activity_id === $lockedActivity->id && $lockedService->active && $lockedService->booking_enabled, 422, 'Servizio non prenotabile.');

            $partySize = (int) $data['party_size'];
            abort_unless($partySize >= $lockedService->min_party_size && $partySize <= $lockedService->max_party_size, 422, 'Numero di persone non ammesso.');
            $startsAt = CarbonImmutable::parse($data['starts_at']);
            abort_if($startsAt->lessThan(now()->addMinutes($lockedService->minimum_advance_minutes)), 422, 'Anticipo minimo non rispettato.');

            $slot = collect($this->availability->forDate($lockedService, $startsAt->startOfDay(), true))
                ->first(fn (array $candidate): bool => CarbonImmutable::parse($candidate['starts_at'])->equalTo($startsAt));
            abort_unless($slot !== null, 422, 'Slot non disponibile.');
            abort_if($slot['available'] < $partySize, 409, 'Capacità non disponibile.');

            $reservation = Reservation::query()->create([
                'ulid' => (string) Str::ulid(), 'user_id' => $user->id, 'activity_id' => $lockedActivity->id,
                'service_id' => $lockedService->id, 'starts_at' => $startsAt, 'party_size' => $partySize,
                'status' => ReservationStatus::Confirmed, 'contact_phone' => $data['contact_phone'],
                'notes' => $data['notes'] ?? null, 'idempotency_key' => $idempotencyKey,
            ]);
            $notification = Notification::query()->create([
                'ulid' => (string) Str::ulid(), 'user_id' => $user->id, 'activity_id' => $lockedActivity->id,
                'reservation_id' => $reservation->id, 'kind' => 'reservation_confirmed', 'channel' => 'internal',
                'template' => 'reservation_confirmed', 'title' => 'Prenotazione confermata',
                'message' => "Prenotazione confermata per {$lockedActivity->name}.", 'status' => 'sent', 'read' => false,
                'payload' => ['reservation_ulid' => $reservation->ulid], 'deduplication_key' => "reservation:{$reservation->id}:confirmed:internal", 'sent_at' => now(),
            ]);
            NotificationDelivery::query()->create(['notification_id' => $notification->id, 'status' => 'sent', 'channel' => 'internal', 'recipient' => (string) $user->id, 'sent_at' => now()]);
            $this->analytics->recordActivity('reservation_created', $lockedActivity, $user, $reservation, "reservation_created:{$reservation->id}");

            return $this->response($reservation);
        });
    }

    private function response(Reservation $reservation): Reservation
    {
        return $reservation->load(['activity.municipality', 'service']);
    }
}
