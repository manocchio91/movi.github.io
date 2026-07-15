<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeasonalEventTemplate extends Model
{
    protected $fillable = [
        'title', 'category_id', 'organizer_type', 'month_from', 'month_to',
        'holiday_code', 'target', 'suggested_description',
        'suggested_price_min', 'suggested_price_max', 'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
