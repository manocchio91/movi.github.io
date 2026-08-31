<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizerFollowerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $pivot = $user->getRelation('pivot');

        return [
            'display_name' => $user->name,
            'followed_at' => $pivot?->getAttribute('created_at'),
        ];
    }
}
