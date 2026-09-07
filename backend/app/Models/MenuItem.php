<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
