<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    protected $fillable = [
        'ulid', 'organizer_profile_id', 'municipality_id', 'name', 'address',
        'latitude', 'longitude',
    ];
}
