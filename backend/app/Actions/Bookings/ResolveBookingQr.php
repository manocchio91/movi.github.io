<?php

namespace App\Actions\Bookings;

use App\Models\Booking;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResolveBookingQr
{
    public function handle(Booking $booking): ?string
    {
        return DB::transaction(function () use ($booking): ?string {
            /** @var Booking $locked */
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            $encrypted = $locked->getRawOriginal('qr_token_encrypted');
            if ($encrypted === null) {
                return null;
            }

            try {
                return Crypt::decryptString((string) $encrypted);
            } catch (DecryptException) {
                $token = Str::random(64);
                DB::table('bookings')->where('id', $locked->id)->update([
                    'qr_token_hash' => Hash::make($token),
                    'qr_token_encrypted' => Crypt::encryptString($token),
                    'updated_at' => now(),
                ]);

                return $token;
            }
        });
    }
}
