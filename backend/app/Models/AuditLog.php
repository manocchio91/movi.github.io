<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $actor_user_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<mixed>|null $metadata
 * @property string|null $ip_address
 * @property string|null $request_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AuditLog extends Model
{
    protected $fillable = [
        'actor_user_id', 'action', 'subject_type', 'subject_id',
        'metadata', 'ip_address', 'request_id',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
