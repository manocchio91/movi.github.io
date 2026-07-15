<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketType extends Model
{
    protected $fillable = [
        'event_id', 'name', 'description', 'audience', 'max_quantity',
        'booked_quantity', 'price_cents', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
