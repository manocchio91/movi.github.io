<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['service_ulid' => ['required', 'ulid', 'exists:activity_services,ulid'], 'starts_at' => ['required', 'date'], 'party_size' => ['required', 'integer', 'min:1', 'max:100'], 'contact_phone' => ['required', 'string', 'max:30'], 'notes' => ['nullable', 'string', 'max:1000']];
    }
}
