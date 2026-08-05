<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizerSubscriptionHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['ulid', 'organizer_subscription_id', 'changed_by_user_id', 'previous_values', 'new_values', 'created_at'];

    protected function casts(): array
    {
        return ['previous_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];
    }
}
