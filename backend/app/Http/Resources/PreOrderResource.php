<?php

namespace App\Http\Resources;

use App\Models\PreOrder;
use App\Models\PreOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreOrder */
class PreOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['ulid' => $this->ulid, 'status' => $this->status, 'total_known_amount' => $this->total_known_amount, 'notes' => $this->notes, 'locked_at' => $this->locked_at, 'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (PreOrderItem $item) => ['menu_item_ulid' => $item->menuItem?->getAttribute('ulid'), ...$item->only(['quantity', 'unit_price_snapshot', 'item_name_snapshot', 'subtotal'])])->values()->all())];
    }
}
