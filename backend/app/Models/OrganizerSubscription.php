<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizerSubscription extends Model
{
    protected $fillable = [
        'ulid', 'organizer_profile_id', 'subscription_plan_id', 'status',
        'trial_ends_at', 'current_period_starts_at', 'current_period_ends_at',
        'cancelled_at', 'external_subscription_id',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function organizerProfile()
    {
        return $this->belongsTo(OrganizerProfile::class);
    }
}
