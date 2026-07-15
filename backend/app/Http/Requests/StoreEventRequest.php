<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['required', 'string', 'max:280'],
            'description' => ['required', 'string', 'max:8000'],
            'municipality_id' => ['required', 'exists:municipalities,id'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'booking_closes_at' => ['nullable', 'date', 'before_or_equal:starts_at'],
            'capacity_total' => ['required', 'integer', 'min:1', 'max:10000'],
            'is_free' => ['required', 'boolean'],
            'price_notes' => ['nullable', 'string', 'max:500'],
            'family_friendly' => ['required', 'boolean'],
            'indoor_outdoor' => ['required', 'in:indoor,outdoor,mixed'],
            'minimum_child_age' => ['nullable', 'integer', 'min:0', 'max:17'],
            'maximum_child_age' => ['nullable', 'integer', 'min:0', 'max:17', 'gte:minimum_child_age'],
        ];
    }
}
