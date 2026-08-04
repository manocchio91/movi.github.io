<?php

namespace App\Actions\Bookings;

use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CheckInBooking
{
    public function handle(User $operator, Event $event, ?string $token, ?string $bookingCode = null): CheckIn
    {
        return DB::transaction(function () use ($operator, $event, $token, $bookingCode): CheckIn {
            $candidates = Booking::query()
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->when($bookingCode !== null, fn ($query) => $query->where('booking_code', $bookingCode))
                ->get();

            $booking = $bookingCode !== null
                ? $candidates->first()
                : $candidates->first(fn (Booking $candidate): bool => $token !== null && Hash::check($token, $candidate->qr_token_hash));

            abort_unless($booking !== null, 404, 'Codice non valido per questo evento.');
            abort_unless(in_array($booking->status, ['confirmed', 'checked_in'], true), 409, 'Prenotazione non valida per il check-in.');
            abort_if($booking->checkIns()->exists(), 409, 'Check-in già effettuato.');

            $checkIn = CheckIn::query()->create([
                'booking_id' => $booking->id,
                'operator_user_id' => $operator->id,
                'checked_in_at' => now(),
            ]);

            $booking->forceFill(['status' => 'checked_in'])->save();

            return $checkIn->load('booking');
        });
    }
}
