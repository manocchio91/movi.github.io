<?php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => PhoneNumber::normalize($this->input('phone'))]);
    }

    public function rules(): array
    {
        return [
            'adults_count' => ['required', 'integer', 'min:0', 'max:50'],
            'children_count' => ['nullable', 'integer', 'min:0', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
            'save_phone_to_profile' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ticket_type_ulid' => ['required', 'string', 'size:26', 'distinct', 'exists:ticket_types,ulid'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Il telefono è obbligatorio.',
            'phone.regex' => 'Inserisci un numero di telefono valido, italiano o internazionale.',
        ];
    }
}
