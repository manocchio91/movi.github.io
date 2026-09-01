<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiscoveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'when' => ['nullable', Rule::in(['today', 'evening', 'tomorrow', 'weekend'])],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'type' => ['nullable', Rule::in(['all', 'event', 'activity'])], 'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'], 'family' => ['nullable', 'boolean'],
            'available' => ['nullable', 'boolean'], 'include_sold_out' => ['nullable', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude,radius_km'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude,radius_km'],
            'radius_km' => ['nullable', 'numeric', 'min:1', 'max:200', 'required_with:latitude,longitude'],
            'sort' => ['nullable', Rule::in(['relevance', 'distance', 'date', 'popularity'])],
            'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'between:1,50'],
        ];
    }
}
