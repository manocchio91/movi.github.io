<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $menu_section_id
 * @property string $name
 * @property string|null $description
 * @property int|null $price_cents
 * @property bool $active
 * @property bool $available
 * @property int $sort_order
 * @property array<mixed>|null $allergens
 * @property array<mixed>|null $dietary_tags
 * @property bool $allergen_information_verified
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MenuItem extends Model
{
    protected $fillable = ['ulid', 'menu_section_id', 'name', 'description', 'price_cents', 'active', 'available', 'sort_order', 'allergens', 'dietary_tags', 'allergen_information_verified'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'available' => 'boolean', 'allergens' => 'array', 'dietary_tags' => 'array', 'allergen_information_verified' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(MenuSection::class, 'menu_section_id');
    }
}
