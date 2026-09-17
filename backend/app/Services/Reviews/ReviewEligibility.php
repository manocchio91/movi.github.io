<?php

namespace App\Services\Reviews;

use App\Enums\ReservationStatus;
use App\Models\ActivityReview;
use App\Models\Booking;
use App\Models\Reservation;
use App\Models\Review;

final class ReviewEligibility
{
    public function activityReservation(Reservation $reservation): bool
    {
        return in_array($reservation->status, [ReservationStatus::Confirmed, ReservationStatus::Completed], true)
            && now()->startOfDay()->gte($reservation->starts_at->copy()->startOfDay()->addDay())
            && ! ActivityReview::query()->where('reservation_id', $reservation->id)->exists()
            && ! ActivityReview::query()->where('activity_id', $reservation->activity_id)->where('user_id', $reservation->user_id)->exists();
    }

    public function eventBooking(Booking $booking): bool
    {
        if (! in_array($booking->status, ['confirmed', 'checked_in', 'completed'], true)) {
            return false;
        }

        $booking->loadMissing('event');
        $concludedAt = $booking->event->ends_at ?? $booking->event->starts_at;

        return $concludedAt->isPast()
            && ! Review::query()->where('booking_id', $booking->id)->exists()
            && ! Review::query()->where('event_id', $booking->event_id)->where('user_id', $booking->user_id)->exists();
    }
}
