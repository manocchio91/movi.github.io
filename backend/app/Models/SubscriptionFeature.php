<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionFeature extends Model
{
    protected $fillable = [
        'subscription_plan_id', 'feature_key', 'name', 'description', 'limits',
    ];

    protected function casts(): array
    {
        return ['limits' => 'array'];
    }
}
