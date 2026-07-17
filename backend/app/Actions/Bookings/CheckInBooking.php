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
    public function handle(User $operator, Event $event, string $token): CheckIn
    {
        return DB::transaction(function () use ($operator, $event, $token): CheckIn {
            $booking = Booking::query()
                ->where('event_id', $event->id)
                ->whereIn('status', ['confirmed', 'checked_in'])
                ->lockForUpdate()
                ->get()
                ->first(fn (Booking $candidate): bool => Hash::check($token, $candidate->qr_token_hash));

            abort_unless($booking, 404, 'QR code non valido.');
            abort_if($booking->checkIns()->exists(), 409, 'QR code già utilizzato.');

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
