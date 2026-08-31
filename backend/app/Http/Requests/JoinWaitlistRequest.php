<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JoinWaitlistRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'adults_count' => ['required', 'integer', 'min:0', 'max:50'],
            'children_count' => ['nullable', 'integer', 'min:0', 'max:50'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ticket_type_ulid' => ['required', 'string', 'size:26', 'distinct', 'exists:ticket_types,ulid'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }
}
