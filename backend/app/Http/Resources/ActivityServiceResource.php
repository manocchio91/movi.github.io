<?php

namespace App\Http\Resources;

use App\Models\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ActivityService $service */
        $service = $this->resource;

        return ['ulid' => $service->ulid, 'name' => $service->name, 'type' => $service->type, 'active' => $service->active, 'booking_enabled' => $service->booking_enabled, 'min_party_size' => $service->min_party_size, 'max_party_size' => $service->max_party_size, 'minimum_advance_minutes' => $service->minimum_advance_minutes, 'indicative_duration_minutes' => $service->indicative_duration_minutes, 'capacity_mode' => $service->capacity_mode, 'rules' => $this->whenLoaded('availabilityRules'), 'exceptions' => $this->whenLoaded('availabilityExceptions')];
    }
}
