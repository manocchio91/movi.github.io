<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:2000'], 'price_cents' => ['nullable', 'integer', 'min:0'], 'active' => ['sometimes', 'boolean'], 'available' => ['sometimes', 'boolean'], 'sort_order' => ['sometimes', 'integer', 'min:0'], 'allergens' => ['nullable', 'array'], 'allergens.*' => ['string', 'max:80'], 'dietary_tags' => ['nullable', 'array'], 'dietary_tags.*' => ['string', 'max:80'], 'allergen_information_verified' => ['sometimes', 'boolean']];
    }
}
