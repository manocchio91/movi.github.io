<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $fillable = [
        'ulid', 'organizer_profile_id', 'event_id', 'name', 'description',
        'starts_at', 'ends_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function statistics()
    {
        return $this->hasOne(CampaignStatistic::class);
    }
}
