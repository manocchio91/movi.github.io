<?php

namespace App\Actions\Auth;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\OrganizerProfile;
use App\Models\User;
use Illuminate\Support\Str;

class CreateOrganizerProfile
{
    /** @param array<string, mixed> $data */
    public function execute(User $user, array $data): OrganizerProfile
    {
        $profile = OrganizerProfile::query()->create([
            'ulid' => (string) Str::ulid(),
            'owner_user_id' => $user->id,
            'name' => $data['organization_name'],
            'type' => $data['organization_type'],
            'email' => $user->email,
            'country' => 'Italia',
            'verification_status' => 'incomplete',
        ]);

        Activity::query()->create([
            'ulid' => (string) Str::ulid(),
            'organizer_profile_id' => $profile->id,
            'name' => $profile->name,
            'slug' => Str::slug($profile->name).'-'.strtolower(Str::random(6)),
            'type' => str_contains(strtolower((string) $profile->type), 'ristor') ? ActivityType::Restaurant : ActivityType::Other,
            'status' => ActivityStatus::Draft,
            'booking_enabled' => false,
        ]);

        return $profile;
    }
}
