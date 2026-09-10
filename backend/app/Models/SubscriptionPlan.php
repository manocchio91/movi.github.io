<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $monthly_price_cents
 * @property int|null $trial_days
 * @property int|null $active_event_limit
 * @property bool $is_public
 * @property bool $is_active
 * @property string|null $external_price_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $billing_model
 * @property string $billing_interval
 * @property int $booking_fee_cents
 */
class SubscriptionPlan extends Model
{
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected $fillable = [
        'ulid', 'name', 'slug', 'description', 'monthly_price_cents',
        'trial_days', 'active_event_limit', 'is_public', 'is_active',
        'external_price_id', 'billing_model', 'billing_interval', 'booking_fee_cents',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SubscriptionFeature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(SubscriptionFeature::class);
    }
}
