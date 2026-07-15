<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiEventSuggestion extends Model
{
    protected $fillable = [
        'ulid', 'organizer_profile_id', 'generated_for_month', 'generated_for_territory',
        'input_context', 'suggestions', 'provider', 'model', 'prompt_version',
        'status', 'latency_ms', 'cost_cents', 'generated_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'input_context' => 'array',
            'suggestions' => 'array',
            'generated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function organizerProfile()
    {
        return $this->belongsTo(OrganizerProfile::class);
    }
}
