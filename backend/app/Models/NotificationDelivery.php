<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $notification_id
 * @property string $status
 * @property string $channel
 * @property string $recipient
 * @property int $retry_count
 * @property string|null $last_error
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class NotificationDelivery extends Model
{
    protected $fillable = [
        'notification_id', 'status', 'channel', 'recipient',
        'retry_count', 'last_error', 'sent_at', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }
}
