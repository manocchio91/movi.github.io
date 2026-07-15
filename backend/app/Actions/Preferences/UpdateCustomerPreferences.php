<?php

namespace App\Actions\Preferences;

use App\Models\User;

class UpdateCustomerPreferences
{
    public function handle(User $user, array $categoryIds): void
    {
        $user->preferredCategories()->sync($categoryIds);
    }
}
