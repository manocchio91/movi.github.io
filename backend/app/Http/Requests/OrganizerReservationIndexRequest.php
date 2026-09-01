<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizerReservationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOrganizer() === true;
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', Rule::in(['today', 'tomorrow'])], 'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'], 'status' => ['nullable', Rule::in(['confirmed', 'cancelled', 'completed', 'no_show'])],
            'activity' => ['nullable', 'ulid'], 'service' => ['nullable', 'ulid'], 'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
