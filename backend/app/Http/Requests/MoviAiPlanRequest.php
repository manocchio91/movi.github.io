<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MoviAiPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['message' => ['required', 'string', 'max:1000'], 'previous_intent' => ['nullable', 'array'],
            'previous_intent.max_steps' => ['nullable', 'integer', 'between:1,3']];
    }
}
