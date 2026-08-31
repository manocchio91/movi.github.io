<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceAvailabilityException extends Model
{
    protected $fillable = ['service_id', 'date', 'is_closed', 'slots', 'note'];

    protected function casts(): array
    {
        return ['date' => 'date', 'is_closed' => 'boolean', 'slots' => 'array'];
    }

    /** @return BelongsTo<ActivityService, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(ActivityService::class, 'service_id');
    }
}
