<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizerOpportunityReport extends Model
{
    protected $fillable = [
        'ulid', 'organizer_profile_id', 'municipality_id', 'starts_at',
        'ends_at', 'input', 'summary', 'suggestions', 'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'input' => 'array',
            'summary' => 'array',
            'suggestions' => 'array',
            'calculated_at' => 'datetime',
        ];
    }
}
