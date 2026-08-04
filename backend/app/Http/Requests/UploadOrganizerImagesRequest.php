<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadOrganizerImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $profile = $this->user()?->organizerProfile;

        return $profile !== null && $this->user()->can('update', $profile);
    }

    public function rules(): array
    {
        return [
            'logo' => ['required_without:cover', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'cover' => ['required_without:logo', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
