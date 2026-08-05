<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAdminOrganizerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['incomplete', 'pending', 'changes_requested', 'verified', 'rejected', 'suspended'])],
            'commercial_status' => ['nullable', Rule::in(['pending', 'active', 'suspended', 'expired', 'cancelled'])],
            'plan' => ['nullable', Rule::in(['unlimited', 'pay_per_booking'])],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
