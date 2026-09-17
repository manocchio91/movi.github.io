<?php

namespace App\Http\Resources;

use App\Enums\ReservationStatus;
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

        $reviewEligible = in_array($reservation->status, [ReservationStatus::Confirmed, ReservationStatus::Completed], true)
            && now()->startOfDay()->gte($reservation->starts_at->copy()->startOfDay()->addDay())
            && $reservation->relationLoaded('review')
            && $reservation->review === null;

        return ['ulid' => $reservation->ulid, 'starts_at' => $reservation->starts_at, 'party_size' => $reservation->party_size, 'status' => $reservation->status, 'created_at' => $reservation->created_at, 'can_cancel' => $reservation->status === ReservationStatus::Confirmed && $reservation->starts_at->isFuture(), 'review_eligible' => $reviewEligible, 'contact_phone' => $this->when($canSeePrivate, $reservation->contact_phone), 'notes' => $this->when($canSeePrivate, $reservation->notes), 'cancelled_at' => $reservation->cancelled_at, 'cancellation_reason' => $reservation->cancellation_reason, 'activity' => ActivityResource::make($this->whenLoaded('activity')), 'service' => ActivityServiceResource::make($this->whenLoaded('service')), 'customer' => $this->whenLoaded('user', fn () => ['ulid' => $reservation->user->getAttribute('ulid'), 'name' => $reservation->user->name]), 'preorder' => $this->whenLoaded('preorder', fn () => $reservation->preorder ? PreOrderResource::make($reservation->preorder) : null)];
    }
}
