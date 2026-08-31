<?php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class AcceptWaitlistOfferRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => PhoneNumber::normalize($this->input('phone'))]);
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'dietary_needs' => ['nullable', 'string', 'max:1000'],
            'accessibility_needs' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
