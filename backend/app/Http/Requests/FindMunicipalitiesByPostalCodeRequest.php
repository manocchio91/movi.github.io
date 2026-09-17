<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FindMunicipalitiesByPostalCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['postal_code' => ['required', 'regex:/^\d{5}$/']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'postal_code.required' => 'Inserisci il CAP.',
            'postal_code.regex' => 'Il CAP deve contenere esattamente 5 cifre.',
        ];
    }
}
