<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckInBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'qr_token' => ['required', 'string', 'min:32', 'max:255'],
        ];
    }
}
