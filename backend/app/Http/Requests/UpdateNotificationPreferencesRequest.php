<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'internal_enabled' => ['required', 'boolean'],
            'email_enabled' => ['required', 'boolean'],
            'promotional_enabled' => ['required', 'boolean'],
            'operational_enabled' => ['required', 'boolean'],
            'followed_organizer_events_enabled' => ['required', 'boolean'],
            'frequency' => ['required', 'in:immediate,daily,weekly'],
        ];
    }
}
