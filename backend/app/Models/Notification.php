<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property int|null $event_id
 * @property int|null $category_id
 * @property string $kind
 * @property string $channel
 * @property string $template
 * @property string $title
 * @property string $message
 * @property string $status
 * @property bool $read
 * @property array<mixed> $payload
 * @property string|null $deduplication_key
 * @property Carbon|null $sent_at
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $activity_id
 * @property int|null $reservation_id
 */
class Notification extends Model
{
    protected $fillable = [
        'ulid', 'user_id', 'event_id', 'activity_id', 'reservation_id', 'category_id', 'kind', 'channel', 'template',
        'title', 'message', 'status', 'read', 'payload', 'deduplication_key',
        'sent_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'read' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return HasMany<NotificationDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }
}
