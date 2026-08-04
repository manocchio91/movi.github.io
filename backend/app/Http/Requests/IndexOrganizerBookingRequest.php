<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexOrganizerBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:120'],
            'filter' => ['sometimes', 'string', 'in:all,confirmed,checked_in,completed,cancelled'],
            'sort_by' => ['sometimes', 'string', 'in:booked_at,event,customer,people,status'],
            'sort' => ['sometimes', 'string', 'in:asc,desc'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
