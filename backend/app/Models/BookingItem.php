<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property int $ticket_type_id
 * @property int $quantity
 * @property int $unit_price_cents
 * @property int $total_price_cents
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BookingItem extends Model
{
    protected $fillable = [
        'booking_id', 'ticket_type_id', 'quantity', 'unit_price_cents', 'total_price_cents',
    ];

    /**
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }
}
