<?php

namespace App\Http\Requests;

use App\Models\Municipality;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOrganizerProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('pec')) {
            $normalized['pec'] = strtolower(trim((string) $this->input('pec')));
        }

        if ($this->has('sdi_code')) {
            $normalized['sdi_code'] = strtoupper(trim((string) $this->input('sdi_code')));
        }

        if ($this->has('vat_number')) {
            $normalized['vat_number'] = strtoupper((string) preg_replace('/[\s.\-]/', '', (string) $this->input('vat_number')));
        }

        if ($this->has('province')) {
            $normalized['province'] = strtoupper(trim((string) $this->input('province')));
        }

        if ($this->filled('municipality_id')) {
            $municipality = Municipality::query()->with('province')->find($this->input('municipality_id'));
            if ($municipality?->province) {
                $normalized['province'] = $municipality->province->code;
            }
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        $safeUrl = ['nullable', 'url', 'starts_with:http://,https://', 'max:255'];

        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:180'],
            'type' => ['sometimes', 'nullable', 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'municipality_id' => ['sometimes', 'nullable', 'integer', 'exists:municipalities,id'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'street_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'postal_code' => ['sometimes', 'nullable', 'regex:/^\d{5}$/'],
            'province' => ['sometimes', 'nullable', 'regex:/^[A-Z]{2}$/'],
            'country' => ['sometimes', 'nullable', 'string', 'max:80'],
            'vat_number' => ['sometimes', 'nullable', 'regex:/^(IT)?\d{11}$/'],
            'pec' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'sdi_code' => ['sometimes', 'nullable', 'alpha_num:ascii', 'size:7'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'public_email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'website_url' => ['sometimes', ...$safeUrl],
            'instagram_url' => ['sometimes', ...$safeUrl],
            'facebook_url' => ['sometimes', ...$safeUrl],
            'tiktok_url' => ['sometimes', ...$safeUrl],
            'youtube_url' => ['sometimes', ...$safeUrl],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->filled('municipality_id') || ! $this->filled('postal_code')) {
                return;
            }

            $municipality = Municipality::query()->find($this->input('municipality_id'));
            if ($municipality && $municipality->postal_code !== $this->input('postal_code')) {
                $validator->errors()->add('postal_code', 'Il CAP non corrisponde al Comune selezionato.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'municipality_id.integer' => 'Il Comune selezionato non è valido.',
            'municipality_id.exists' => 'Il Comune selezionato non esiste.',
            'postal_code.regex' => 'Il CAP deve contenere esattamente 5 cifre.',
            'province.regex' => 'La Provincia deve contenere una sigla di 2 lettere.',
            'vat_number.regex' => 'La Partita IVA deve contenere 11 cifre, con prefisso IT facoltativo.',
            'pec.email' => 'La PEC deve essere un indirizzo email valido.',
            'sdi_code.alpha_num' => 'Il Codice SDI deve contenere solo lettere e numeri.',
            'sdi_code.size' => 'Il Codice SDI deve contenere esattamente 7 caratteri.',
            '*.url' => 'Inserisci un indirizzo web valido.',
            '*.starts_with' => 'L’indirizzo web deve iniziare con http:// oppure https://.',
        ];
    }
}
