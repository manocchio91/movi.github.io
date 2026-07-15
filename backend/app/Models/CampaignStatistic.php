<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignStatistic extends Model
{
    protected $fillable = [
        'campaign_id', 'views', 'clicks', 'bookings_generated',
        'conversion_rate', 'calculated_at',
    ];

    protected function casts(): array
    {
        return ['calculated_at' => 'datetime'];
    }
}
