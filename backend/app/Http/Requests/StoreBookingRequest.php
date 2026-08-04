<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'adults_count' => ['required', 'integer', 'min:0', 'max:50'],
            'children_count' => ['nullable', 'integer', 'min:0', 'max:50'],
            'children_age_ranges' => ['nullable', 'array'],
            'dietary_needs' => ['nullable', 'string', 'max:1000'],
            'accessibility_needs' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ticket_type_ulid' => ['required', 'string', 'size:26', 'exists:ticket_types,ulid'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }
}
