<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
