<?php

namespace App\Http\Resources;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Activity $activity */
        $activity = $this->resource;

        return [
            'ulid' => $activity->ulid, 'name' => $activity->name, 'slug' => $activity->slug, 'type' => $activity->type,
            'description' => $activity->description, 'address' => $activity->address, 'postal_code' => $activity->postal_code,
            'phone' => $activity->phone, 'public_email' => $activity->public_email, 'status' => $activity->status,
            'booking_enabled' => $activity->booking_enabled, 'municipality' => $this->whenLoaded('municipality'),
            'organizer' => $this->whenLoaded('organizerProfile', fn () => ['ulid' => $activity->organizerProfile->ulid, 'name' => $activity->organizerProfile->name]),
            'services' => ActivityServiceResource::collection($this->whenLoaded('services')),
            'followers_count' => $this->whenCounted('followers'), 'is_following' => $activity->relationLoaded('followers') ? $activity->followers->isNotEmpty() : false,
            'reviews_count' => $this->whenCounted('reviews'), 'average_rating' => $this->whenAggregated('reviews', 'rating', 'avg'),
        ];
    }
}
