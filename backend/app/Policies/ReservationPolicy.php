<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function view(User $user, Reservation $reservation): bool
    {
        return $reservation->user_id === $user->id || $reservation->activity?->organizerProfile?->owner_user_id === $user->id || $user->isAdmin();
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
        return $this->view($user, $reservation);
    }
}
