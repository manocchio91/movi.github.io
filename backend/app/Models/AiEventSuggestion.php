<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $organizer_profile_id
 * @property string $generated_for_month
 * @property string|null $generated_for_territory
 * @property array<mixed> $input_context
 * @property array<mixed> $suggestions
 * @property string $provider
 * @property string|null $model
 * @property string $prompt_version
 * @property string $status
 * @property int $latency_ms
 * @property int $cost_cents
 * @property Carbon $generated_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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
