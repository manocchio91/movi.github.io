<?php

namespace App\Actions\Auth;

use App\Models\OrganizerProfile;
use App\Models\User;
use Illuminate\Support\Str;

class CreateOrganizerProfile
{
    /** @param array<string, mixed> $data */
    public function execute(User $user, array $data): OrganizerProfile
    {
        return OrganizerProfile::query()->create([
            'ulid' => (string) Str::ulid(),
            'owner_user_id' => $user->id,
            'name' => $data['organization_name'],
            'type' => $data['organization_type'],
            'email' => $user->email,
            'country' => 'Italia',
            'verification_status' => 'incomplete',
        ]);
    }
}
