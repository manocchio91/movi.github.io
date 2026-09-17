<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('organizer_profiles')
            ->where('verification_status', 'verified')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('activities')
                ->whereColumn('activities.organizer_profile_id', 'organizer_profiles.id'))
            ->orderBy('id')
            ->each(function (object $profile): void {
                DB::table('activities')->insert([
                    'ulid' => (string) Str::ulid(),
                    'organizer_profile_id' => $profile->id,
                    'municipality_id' => $profile->municipality_id,
                    'name' => $profile->name,
                    'slug' => Str::slug($profile->name).'-'.Str::lower(Str::random(6)),
                    'type' => str_contains(Str::lower((string) $profile->type), 'ristor') ? 'restaurant' : 'other',
                    'description' => $profile->description,
                    'address' => trim(implode(' ', array_filter([$profile->address, $profile->street_number]))),
                    'postal_code' => $profile->postal_code,
                    'phone' => $profile->phone,
                    'public_email' => $profile->public_email,
                    'status' => 'published',
                    'booking_enabled' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Data repair is intentionally irreversible: existing Activities may acquire live relations.
    }
};
