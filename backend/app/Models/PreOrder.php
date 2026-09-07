<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property string $ulid @property \Illuminate\Support\Carbon|null $locked_at */
class PreOrder extends Model
{
    protected $table = 'preorders';

    protected $fillable = ['ulid', 'reservation_id', 'status', 'total_known_amount', 'notes', 'locked_at'];

    protected function casts(): array
    {
        return ['locked_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PreOrderItem::class, 'preorder_id');
    }
}
