<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'booking_id' => ['sometimes', 'integer', 'exists:bookings,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'experience_quality_rating' => ['required', 'integer', 'between:1,5'],
            'organization_rating' => ['required', 'integer', 'between:1,5'],
            'location_rating' => ['required', 'integer', 'between:1,5'],
            'value_rating' => ['required', 'integer', 'between:1,5'],
            'schedule_respect_rating' => ['required', 'integer', 'between:1,5'],
            'staff_rating' => ['required', 'integer', 'between:1,5'],
            'suitable_for_children' => ['nullable', 'boolean'],
            'would_recommend' => ['required', 'boolean'],
            'comment' => ['nullable', 'string', 'max:3000'],
            'images' => ['sometimes', 'array', 'max:6'],
            'images.*.path' => ['required_with:images', 'string', 'max:2048'],
            'images.*.disk' => ['sometimes', 'string', 'max:40'],
            'images.*.mime_type' => ['sometimes', 'nullable', 'string', 'max:120'],
            'images.*.size_bytes' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
