<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['required', 'string', 'max:280'],
            'description' => ['required', 'string', 'max:8000'],
            'program' => ['nullable', 'string', 'max:8000'],
            'included' => ['nullable', 'string', 'max:4000'],
            'not_included' => ['nullable', 'string', 'max:4000'],
            'important_information' => ['nullable', 'string', 'max:4000'],
            'requirements' => ['nullable', 'string', 'max:4000'],
            'recommended_age_range' => ['nullable', 'string', 'max:120'],
            'equipment_notes' => ['nullable', 'string', 'max:2000'],
            'allergy_notes' => ['nullable', 'string', 'max:2000'],
            'cancellation_policy' => ['nullable', 'string', 'max:3000'],
            'organizer_contact' => ['nullable', 'string', 'max:255'],
            'show_in_inspiration_feed' => ['nullable', 'boolean'],
            'municipality_id' => ['required', 'exists:municipalities,id'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'booking_closes_at' => ['nullable', 'date', 'before_or_equal:starts_at'],
            'capacity_total' => ['required', 'integer', 'min:1', 'max:10000'],
            'is_free' => ['required', 'boolean'],
            'price_notes' => ['nullable', 'string', 'max:500'],
            'family_friendly' => ['required', 'boolean'],
            'indoor_outdoor' => ['required', 'in:indoor,outdoor,mixed'],
            'minimum_child_age' => ['nullable', 'integer', 'min:0', 'max:17'],
            'maximum_child_age' => ['nullable', 'integer', 'min:0', 'max:17', 'gte:minimum_child_age'],
            'ticket_types' => ['nullable', 'array'],
            'ticket_types.*.name' => ['required_with:ticket_types', 'string', 'max:120'],
            'ticket_types.*.description' => ['nullable', 'string', 'max:500'],
            'ticket_types.*.audience' => ['required_with:ticket_types', 'in:adult,child,generic'],
            'ticket_types.*.price_cents' => ['required_with:ticket_types', 'integer', 'min:0'],
            'ticket_types.*.max_quantity' => ['nullable', 'integer', 'min:1'],
            'ticket_types.*.is_active' => ['nullable', 'boolean'],
        ];
    }
}
