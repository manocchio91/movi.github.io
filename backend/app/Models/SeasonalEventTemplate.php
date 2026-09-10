<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property int|null $category_id
 * @property string|null $organizer_type
 * @property int $month_from
 * @property int $month_to
 * @property string|null $holiday_code
 * @property string|null $target
 * @property string|null $suggested_description
 * @property int $suggested_price_min
 * @property int $suggested_price_max
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Category|null $category
 */
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

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
