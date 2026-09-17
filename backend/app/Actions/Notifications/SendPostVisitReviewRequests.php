<?php

namespace App\Actions\Notifications;

use App\Models\Booking;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Reviews\ReviewEligibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class SendPostVisitReviewRequests
{
    public function __construct(private readonly ReviewEligibility $eligibility) {}

    public function handle(): int
    {
        $sent = 0;

        Reservation::query()->whereIn('status', ['confirmed', 'completed'])->where('starts_at', '<', today())
            ->with(['activity', 'user.notificationPreference'])->chunkById(100, function ($reservations) use (&$sent): void {
                foreach ($reservations as $reservation) {
                    if ($this->eligibility->activityReservation($reservation)) {
                        $sent += $this->notify($reservation->user, $reservation, 'activity_review_request', 'Recensisci il locale',
                            "Com’è stata la tua esperienza da {$reservation->activity->name}?", "/customer/reservations?review={$reservation->ulid}");
                    }
                }
            });

        Booking::query()->whereIn('status', ['confirmed', 'checked_in', 'completed'])
            ->whereHas('event', fn ($query) => $query->where(fn ($dates) => $dates->where('ends_at', '<', now())->orWhere(fn ($start) => $start->whereNull('ends_at')->where('starts_at', '<', now()))))
            ->with(['event', 'user.notificationPreference'])->chunkById(100, function ($bookings) use (&$sent): void {
                foreach ($bookings as $booking) {
                    if ($this->eligibility->eventBooking($booking)) {
                        $sent += $this->notify($booking->user, $booking, 'event_review_request', 'Recensisci l’evento',
                            "Raccontaci com’è stato {$booking->event->title}.", "/customer/bookings?review={$booking->ulid}");
                    }
                }
            });

        return $sent;
    }

    private function notify(User $user, Model $experience, string $kind, string $title, string $message, string $url): int
    {
        $preference = $user->notificationPreference;
        if ($preference && ! $preference->operational_enabled) {
            return 0;
        }
        $channels = $preference
            ? array_keys(array_filter(['internal' => $preference->internal_enabled, 'email' => $preference->email_enabled]))
            : ['internal'];
        $created = 0;

        foreach ($channels as $channel) {
            $key = "{$kind}:{$experience->getTable()}:{$experience->getKey()}:{$channel}";
            $attributes = ['deduplication_key' => $key];
            $values = ['ulid' => (string) Str::ulid(), 'user_id' => $user->id,
                'event_id' => $experience instanceof Booking ? $experience->event_id : null,
                'activity_id' => $experience instanceof Reservation ? $experience->activity_id : null,
                'reservation_id' => $experience instanceof Reservation ? $experience->id : null,
                'kind' => $kind, 'channel' => $channel, 'template' => $kind, 'title' => $title, 'message' => $message,
                'status' => 'sent', 'read' => false, 'payload' => ['url' => $url], 'sent_at' => now()];
            $notification = Notification::query()->firstOrCreate($attributes, $values);
            NotificationDelivery::query()->firstOrCreate(['notification_id' => $notification->id, 'channel' => $channel],
                ['status' => 'sent', 'recipient' => $channel === 'email' ? $user->email : (string) $user->id, 'sent_at' => now()]);
            $created += $notification->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }
}
