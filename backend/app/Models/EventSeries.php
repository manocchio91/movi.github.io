<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventSeries extends Model
{
    protected $fillable = ['organizer_profile_id', 'name', 'description', 'category_id', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
