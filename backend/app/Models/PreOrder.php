<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $reservation_id
 * @property string $status
 * @property int $total_known_amount
 * @property string|null $notes
 * @property Carbon|null $locked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return HasMany<PreOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PreOrderItem::class, 'preorder_id');
    }
}
