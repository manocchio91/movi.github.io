<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'ulid', 'user_id', 'event_id', 'category_id', 'kind', 'channel', 'template',
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

    public function deliveries()
    {
        return $this->hasMany(NotificationDelivery::class);
    }
}
