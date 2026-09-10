<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $event_id
 * @property string $name
 * @property string|null $description
 * @property string $audience
 * @property int|null $max_quantity
 * @property int $booked_quantity
 * @property int $price_cents
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $ulid
 */
class TicketType extends Model
{
    protected $fillable = [
        'ulid', 'event_id', 'name', 'description', 'audience', 'max_quantity',
        'booked_quantity', 'price_cents', 'is_active',
    ];

    protected static function booted(): void
    {
        static::creating(function (TicketType $ticket): void {
            $ticket->ulid ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
