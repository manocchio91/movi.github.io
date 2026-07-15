<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExperienceView extends Model
{
    protected $fillable = ['event_id', 'user_id', 'session_id', 'source', 'viewed_at'];

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }
}
