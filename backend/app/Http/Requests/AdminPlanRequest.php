<?php

namespace App\Http\Requests;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        /** @var SubscriptionPlan|null $plan */
        $plan = $this->route('plan');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('subscription_plans')->ignore($plan?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'billing_model' => ['required', Rule::in(['unlimited', 'pay_per_booking'])],
            'billing_interval' => ['required', Rule::in(['monthly', 'yearly'])],
            'monthly_price_cents' => ['required', 'integer', 'min:0'],
            'booking_fee_cents' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
