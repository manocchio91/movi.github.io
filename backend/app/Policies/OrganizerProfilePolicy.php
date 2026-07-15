<?php

namespace App\Policies;

use App\Models\OrganizerProfile;
use App\Models\User;

class OrganizerProfilePolicy
{
    public function view(User $user, OrganizerProfile $profile): bool
    {
        return $user->isAdmin() || $profile->owner_user_id === $user->id;
    }

    public function update(User $user, OrganizerProfile $profile): bool
    {
        return $profile->owner_user_id === $user->id;
    }

    public function moderate(User $user): bool
    {
        return $user->isAdmin();
    }
}
