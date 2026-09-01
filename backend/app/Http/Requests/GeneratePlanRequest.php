<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GeneratePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'when' => ['nullable', Rule::in(['today', 'evening', 'tomorrow', 'weekend'])], 'date' => ['nullable', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'date_format:H:i'], 'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'with' => ['nullable', Rule::in(['couple', 'family', 'friends'])], 'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'], 'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude,radius_km'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude,radius_km'], 'radius_km' => ['nullable', 'numeric', 'between:1,200'],
            'budget_cents' => ['nullable', 'integer', 'min:0'], 'intent' => ['nullable', Rule::in(['evening', 'half_day', 'day'])],
        ];
    }
}
