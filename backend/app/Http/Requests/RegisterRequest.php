<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', 'in:customer,organizer'],
            'organization_name' => [Rule::requiredIf($this->input('role') === 'organizer'), 'nullable', 'string', 'max:180'],
            'organization_type' => [Rule::requiredIf($this->input('role') === 'organizer'), 'nullable', 'string', 'max:80'],
        ];
    }
}
