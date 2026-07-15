<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingItem extends Model
{
    protected $fillable = [
        'booking_id', 'ticket_type_id', 'quantity', 'unit_price_cents', 'total_price_cents',
    ];

    public function ticketType()
    {
        return $this->belongsTo(TicketType::class);
    }
}
