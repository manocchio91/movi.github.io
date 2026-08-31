<?php

namespace App\Http\Requests;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'], 'type' => ['required', Rule::enum(ActivityType::class)],
            'description' => ['nullable', 'string', 'max:5000'], 'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'address' => ['nullable', 'string', 'max:255'], 'postal_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:30'], 'public_email' => ['nullable', 'email', 'max:255'],
            'status' => ['sometimes', Rule::enum(ActivityStatus::class)], 'booking_enabled' => ['required', 'boolean'],
        ];
    }
}
