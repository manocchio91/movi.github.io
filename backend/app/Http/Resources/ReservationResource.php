<?php

namespace App\Http\Resources;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Reservation $reservation */
        $reservation = $this->resource;
        $canSeePrivate = $request->user()?->id === $reservation->user_id || $request->user()?->isOrganizer() || $request->user()?->isAdmin();

        return ['ulid' => $reservation->ulid, 'starts_at' => $reservation->starts_at, 'party_size' => $reservation->party_size, 'status' => $reservation->status, 'contact_phone' => $this->when($canSeePrivate, $reservation->contact_phone), 'notes' => $this->when($canSeePrivate, $reservation->notes), 'cancelled_at' => $reservation->cancelled_at, 'cancellation_reason' => $reservation->cancellation_reason, 'activity' => ActivityResource::make($this->whenLoaded('activity')), 'service' => ActivityServiceResource::make($this->whenLoaded('service')), 'customer' => $this->whenLoaded('user', fn () => ['ulid' => $reservation->user->getAttribute('ulid'), 'name' => $reservation->user->name])];
    }
}
