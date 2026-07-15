<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizerFollower extends Model
{
    protected $fillable = ['organizer_profile_id', 'user_id'];
}
