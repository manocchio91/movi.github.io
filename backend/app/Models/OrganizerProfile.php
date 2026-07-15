<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizerProfile extends Model
{
    protected $fillable = [
        'ulid', 'owner_user_id', 'name', 'type', 'description', 'email', 'phone',
        'website_url', 'municipality_id', 'address', 'verification_status', 'verified_at',
    ];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }
}
