<?php

namespace App\Http\Resources;

use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Menu */
class MenuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $organizerView = $request->routeIs('organizer.*');

        // @phpstan-ignore-next-line Eloquent relations are eagerly loaded for serialization.
        return ['ulid' => $this->ulid, 'name' => $this->name, 'status' => $this->status, 'active' => $this->active, 'sections' => $this->whenLoaded('sections', fn () => $this->sections->map(fn ($section) => ['ulid' => $section->ulid, 'name' => $section->name, 'sort_order' => $section->sort_order, 'items' => $section->items->map(fn ($item) => ['ulid' => $item->ulid, 'name' => $item->name, 'description' => $item->description, 'price_cents' => $item->price_cents, 'active' => $item->active, 'available' => $item->available, 'sort_order' => $item->sort_order, 'allergens' => $organizerView || $item->allergen_information_verified ? $item->allergens : null, 'dietary_tags' => $item->dietary_tags, 'allergen_information_verified' => $item->allergen_information_verified])->values()])->values())];
    }
}
