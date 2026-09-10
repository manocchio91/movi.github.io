<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $service_id
 * @property Carbon $date
 * @property bool $is_closed
 * @property array<mixed>|null $slots
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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
