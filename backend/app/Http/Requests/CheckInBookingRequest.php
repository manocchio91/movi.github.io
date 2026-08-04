<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckInBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'qr_token' => ['required_without:booking_code', 'nullable', 'string', 'min:32', 'max:255'],
            'booking_code' => ['required_without:qr_token', 'nullable', 'string', 'max:40'],
        ];
    }
}
