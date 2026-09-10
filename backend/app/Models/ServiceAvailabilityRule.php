<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $service_id
 * @property int $weekday
 * @property string $start_time
 * @property int $capacity
 * @property bool $active
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ServiceAvailabilityRule extends Model
{
    protected $fillable = ['service_id', 'weekday', 'start_time', 'capacity', 'active', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'effective_from' => 'date', 'effective_to' => 'date'];
    }

    /** @return BelongsTo<ActivityService, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(ActivityService::class, 'service_id');
    }
}
