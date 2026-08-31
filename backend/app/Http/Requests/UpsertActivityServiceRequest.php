<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertActivityServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'], 'type' => ['required', Rule::in(['lunch', 'dinner', 'generic'])],
            'active' => ['required', 'boolean'], 'booking_enabled' => ['required', 'boolean'],
            'min_party_size' => ['required', 'integer', 'min:1', 'max:100'],
            'max_party_size' => ['required', 'integer', 'gte:min_party_size', 'max:100'],
            'minimum_advance_minutes' => ['required', 'integer', 'min:0', 'max:525600'],
            'indicative_duration_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
        ];
    }
}
