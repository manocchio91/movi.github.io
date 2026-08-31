<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

class ActivityPolicy
{
    public function update(User $user, Activity $activity): bool
    {
        return $activity->organizerProfile?->owner_user_id === $user->id;
    }

    public function viewReservations(User $user, Activity $activity): bool
    {
        return $this->update($user, $activity) || $user->isAdmin();
    }
}
