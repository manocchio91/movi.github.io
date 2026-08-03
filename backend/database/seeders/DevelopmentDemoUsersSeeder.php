<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\OrganizerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DevelopmentDemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DevelopmentDemoUsersSeeder can only run locally.');
        }

        DB::transaction(function (): void {
            User::query()->updateOrCreate(
                ['email' => 'customer@app-eventi.test'],
                [
                    'name' => 'Customer sviluppo locale',
                    'password' => Hash::make('password'),
                    'role' => UserRole::Customer,
                    'status' => 'active',
                    'email_verified_at' => now(),
                ],
            );

            $organizer = User::query()->updateOrCreate(
                ['email' => 'organizer@app-eventi.test'],
                [
                    'name' => 'Organizer sviluppo locale',
                    'password' => Hash::make('password'),
                    'role' => UserRole::Organizer,
                    'status' => 'active',
                    'email_verified_at' => now(),
                ],
            );

            OrganizerProfile::query()->updateOrCreate(
                ['owner_user_id' => $organizer->id],
                [
                    'ulid' => $organizer->organizerProfile?->ulid ?? (string) Str::ulid(),
                    'name' => 'Organizer sviluppo locale',
                    'type' => 'associazione',
                    'email' => 'organizer@app-eventi.test',
                    'address' => 'Via Demo',
                    'street_number' => '10',
                    'postal_code' => '86100',
                    'province' => 'CB',
                    'country' => 'Italia',
                    'vat_number' => 'IT00000000000',
                    'pec' => 'organizer.demo@pec.example.test',
                    'sdi_code' => '0000000',
                    'public_email' => 'info.organizer@example.test',
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                    'suspended_at' => null,
                ],
            );
        });
    }
}
