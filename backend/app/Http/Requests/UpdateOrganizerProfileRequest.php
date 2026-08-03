<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}
