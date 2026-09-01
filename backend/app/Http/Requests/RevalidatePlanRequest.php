<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RevalidatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['steps' => ['required', 'array', 'between:1,3'], 'steps.*.entity_type' => ['required', 'in:event,activity'], 'steps.*.entity_id' => ['required', 'ulid'], 'steps.*.service_id' => ['nullable', 'ulid'], 'steps.*.start' => ['required', 'date']];
    }
}
