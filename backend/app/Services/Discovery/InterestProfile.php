<?php

namespace App\Services\Discovery;

use App\Models\User;

final readonly class InterestProfile
{
    public function __construct(public array $categoryIds, public array $organizerIds, public array $activityIds) {}

    public static function for(?User $user): self
    {
        if ($user === null) {
            return new self([], [], []);
        }

        return new self(
            $user->preferredCategories()->pluck('categories.id')->map(fn ($id) => (int) $id)->all(),
            $user->followedOrganizers()->pluck('organizer_profiles.id')->map(fn ($id) => (int) $id)->all(),
            $user->followedActivities()->pluck('activities.id')->map(fn ($id) => (int) $id)->all(),
        );
    }
}
