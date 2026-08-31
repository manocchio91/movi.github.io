<?php

namespace App\Http\Resources;

use App\Models\OrganizerProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CustomerFollowingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var OrganizerProfile $organizer */
        $organizer = $this->resource;
        $pivot = $organizer->getRelation('pivot');

        return [
            'ulid' => $organizer->ulid,
            'name' => $organizer->name,
            'type' => $organizer->type,
            'municipality' => $organizer->municipality?->only(['name']),
            'logo_url' => $organizer->logo_path ? Storage::disk('public')->url($organizer->logo_path) : null,
            'followed_at' => $pivot?->getAttribute('created_at'),
        ];
    }
}
