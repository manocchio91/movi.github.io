<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Municipality extends Model
{
    protected $fillable = ['province_id', 'name', 'istat_code', 'latitude', 'longitude'];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }
}
