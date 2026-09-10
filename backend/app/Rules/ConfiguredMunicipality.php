<?php

namespace App\Rules;

use App\Models\Municipality;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ConfiguredMunicipality implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = Municipality::query()
            ->whereKey($value)
            ->whereHas('province.region', fn ($query) => $query->where('code', config('territory.region_code')))
            ->exists();

        if (! $exists) {
            $fail('Il comune selezionato non appartiene al territorio attualmente disponibile.');
        }
    }
}
