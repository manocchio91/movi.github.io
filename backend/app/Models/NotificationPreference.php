<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property bool $internal_enabled
 * @property bool $email_enabled
 * @property bool $promotional_enabled
 * @property string $frequency
 * @property array<mixed>|null $preferred_days
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property bool $operational_enabled
 * @property bool $followed_organizer_events_enabled
 */
class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id', 'internal_enabled', 'email_enabled', 'promotional_enabled',
        'operational_enabled', 'followed_organizer_events_enabled', 'frequency', 'preferred_days',
    ];

    protected function casts(): array
    {
        return [
            'internal_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'promotional_enabled' => 'boolean',
            'operational_enabled' => 'boolean',
            'followed_organizer_events_enabled' => 'boolean',
            'preferred_days' => 'array',
        ];
    }
}
