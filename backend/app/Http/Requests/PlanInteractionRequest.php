<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlanInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['action' => ['required', 'in:plan_opened,plan_step_opened,ai_plan_opened'], 'plan_id' => ['required', 'string', 'max:64'], 'entity_type' => ['nullable', 'in:event,activity'], 'entity_id' => ['nullable', 'ulid']];
    }
}
