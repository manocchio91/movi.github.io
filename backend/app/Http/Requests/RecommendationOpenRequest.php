<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecommendationOpenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['entity_type' => ['required', Rule::in(['event', 'activity'])], 'entity_id' => ['required', 'ulid']];
    }
}
