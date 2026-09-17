<?php

namespace App\Http\Resources;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'categories' => $this->whenLoaded('categories'),
            'organizer' => $this->whenLoaded('organizerProfile', fn () => [
                'ulid' => $activity->organizerProfile->ulid, 'name' => $activity->organizerProfile->name,
                'logo_url' => $activity->organizerProfile->logo_path ? Storage::disk('public')->url($activity->organizerProfile->logo_path) : null,
                'cover_url' => $activity->organizerProfile->cover_path ? Storage::disk('public')->url($activity->organizerProfile->cover_path) : null,
                'website_url' => $activity->organizerProfile->website_url, 'instagram_url' => $activity->organizerProfile->instagram_url,
                'facebook_url' => $activity->organizerProfile->facebook_url,
            ]),
            'services' => ActivityServiceResource::collection($this->whenLoaded('services')),
            'next_available_at' => $activity->getAttribute('next_available_at'),
            'events' => $this->whenLoaded('events', fn (): array => $activity->events->map(fn ($event): array => [
                'ulid' => $event->ulid, 'title' => $event->title, 'summary' => $event->summary, 'starts_at' => $event->starts_at,
                'status' => $event->status, 'available_total' => $event->available_total,
                'image_url' => $event->image_path ? Storage::disk('public')->url($event->image_path) : null,
                'municipality' => $event->municipality, 'categories' => $event->categories,
            ])->values()->all()),
            'followers_count' => $this->whenCounted('followers'), 'is_following' => $activity->relationLoaded('followers') ? $activity->followers->isNotEmpty() : false,
            'reviews_count' => $this->whenCounted('reviews'), 'average_rating' => $this->whenAggregated('reviews', 'rating', 'avg'),
        ];
    }
}
