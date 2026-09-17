<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class OrganizerReservationAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOrganizer() === true;
    }

    public function rules(): array
    {
        return ['period' => ['nullable', Rule::in(['7', '30', '90', 'custom'])],
            'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from']];
    }
}
