<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfigureAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rules' => ['sometimes', 'array'], 'rules.*.weekday' => ['required', 'integer', 'between:1,7'],
            'rules.*.start_time' => ['required', 'date_format:H:i'], 'rules.*.capacity' => ['required', 'integer', 'min:0', 'max:10000'],
            'rules.*.active' => ['sometimes', 'boolean'], 'exceptions' => ['sometimes', 'array'],
            'exceptions.*.date' => ['required', 'date_format:Y-m-d'], 'exceptions.*.is_closed' => ['required', 'boolean'],
            'exceptions.*.slots' => ['nullable', 'array'], 'exceptions.*.slots.*.start_time' => ['required_with:exceptions.*.slots', 'date_format:H:i'],
            'exceptions.*.slots.*.capacity' => ['required_with:exceptions.*.slots', 'integer', 'min:0', 'max:10000'],
            'exceptions.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
