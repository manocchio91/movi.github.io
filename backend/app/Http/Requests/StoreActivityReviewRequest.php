<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreActivityReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reservation_ulid' => ['required_without:booking_ulid', 'nullable', 'ulid', 'exists:reservations,ulid'], 'booking_ulid' => ['required_without:reservation_ulid', 'nullable', 'ulid', 'exists:bookings,ulid'], 'rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:3000'], 'would_recommend' => ['required', 'boolean']];
    }
}
