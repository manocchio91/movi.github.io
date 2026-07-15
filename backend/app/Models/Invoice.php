<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'ulid', 'organizer_subscription_id', 'amount_cents', 'currency',
        'status', 'external_invoice_id', 'issued_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
