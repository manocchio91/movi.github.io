<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignCommercialPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'plan_ulid' => ['required', 'exists:subscription_plans,ulid'],
            'status' => ['required', Rule::in(['pending', 'active', 'suspended', 'expired', 'cancelled'])],
            'agreement_starts_at' => ['nullable', 'date'],
            'agreement_ends_at' => ['nullable', 'date', 'after_or_equal:agreement_starts_at'],
            'agreement_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
