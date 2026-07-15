<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
