<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /** @return BelongsTo<OrganizerProfile, $this> */
    public function organizerProfile(): BelongsTo
    {
        return $this->belongsTo(OrganizerProfile::class);
    }
}
