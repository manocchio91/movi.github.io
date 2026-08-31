<?php

namespace App\Http\Resources;

use App\Models\WaitlistEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WaitlistEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var WaitlistEntry $entry */
        $entry = $this->resource;
        $position = $entry->status === 'waiting'
            ? WaitlistEntry::query()->where('event_id', $entry->event_id)->where('status', 'waiting')
                ->where(function ($query) use ($entry): void {
                    $query->where('created_at', '<', $entry->created_at)
                        ->orWhere(fn ($sameTime) => $sameTime->where('created_at', $entry->created_at)->where('id', '<=', $entry->id));
                })->count()
            : null;

        return [
            'ulid' => $entry->ulid,
            'event_ulid' => $entry->event?->ulid,
            'status' => $entry->status,
            'requested_places' => $entry->requested_places,
            'position' => $position,
            'reserved_until' => $entry->reserved_until,
            'booking_ulid' => $entry->booking?->ulid,
        ];
    }
}
