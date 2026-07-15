<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewReport extends Model
{
    protected $fillable = ['review_id', 'user_id', 'reason', 'details', 'status'];
}
