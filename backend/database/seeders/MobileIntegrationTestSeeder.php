<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\OrganizerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class MobileIntegrationTestSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Mobile integration fixtures are limited to local/testing environments.');
        }

        $owner = User::query()->updateOrCreate(
            ['email' => 'mobile-pending@example.test'],
            [
                'name' => 'Organizer Mobile Pending',
                'password' => Hash::make(Str::random(40)),
                'role' => UserRole::Organizer,
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        OrganizerProfile::query()->updateOrCreate(
            ['owner_user_id' => $owner->id],
            [
                'ulid' => $owner->organizerProfile?->ulid ?? (string) Str::ulid(),
                'name' => 'Organizer Mobile Pending',
                'type' => 'associazione',
                'email' => 'mobile-pending@example.test',
                'address' => 'Via Test Mobile 1',
                'country' => 'Italia',
                'verification_status' => 'pending',
                'verified_at' => null,
            ],
        );

        $qrToken = 'mobile-integration-qr-token';
        Booking::query()->where('booking_code', 'EVT-DEMO01')->update([
            'qr_token_hash' => Hash::make($qrToken),
            'qr_token_encrypted' => $qrToken,
        ]);
    }
}
