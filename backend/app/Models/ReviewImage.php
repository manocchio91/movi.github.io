<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewImage extends Model
{
    protected $fillable = ['review_id', 'disk', 'path', 'mime_type', 'size_bytes'];
}
