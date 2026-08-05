<?php

namespace App\Services\BusinessIntelligence;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\OrganizerProfile;
use App\Models\OrganizerSubscription;
use App\Models\User;

class AdminBusinessMetrics
{
    public const VALID_BOOKINGS = ['confirmed', 'checked_in', 'completed'];

    public function calculate(): array
    {
        $valid = Booking::query()->whereIn('status', self::VALID_BOOKINGS);
        $subscriptions = OrganizerSubscription::query()->with('plan')->where('status', 'active')->get();
        $payPerBookingRevenue = $subscriptions->sum(function (OrganizerSubscription $agreement): int {
            if ($agreement->plan?->billing_model !== 'pay_per_booking') {
                return 0;
            }
            $participants = Booking::query()->whereHas('event', fn ($query) => $query->where('organizer_profile_id', $agreement->organizer_profile_id))->whereIn('status', self::VALID_BOOKINGS)->sum('total_people');

            return $participants * ($agreement->booking_fee_cents ?? $agreement->plan->booking_fee_cents);
        });
        $unlimitedRevenue = $subscriptions->where('plan.billing_model', 'unlimited')->sum(fn (OrganizerSubscription $agreement) => $agreement->plan->monthly_price_cents);

        return [
            'organizers' => ['total' => OrganizerProfile::query()->count(), 'approved' => OrganizerProfile::query()->where('verification_status', 'verified')->count(), 'pending' => OrganizerProfile::query()->where('verification_status', 'pending')->count(), 'suspended' => OrganizerProfile::query()->where('verification_status', 'suspended')->count(), 'commercially_active' => $subscriptions->count(), 'unlimited' => $subscriptions->where('plan.billing_model', 'unlimited')->count(), 'pay_per_booking' => $subscriptions->where('plan.billing_model', 'pay_per_booking')->count()],
            'customers' => ['total' => User::query()->where('role', UserRole::Customer->value)->count(), 'active' => User::query()->where('role', UserRole::Customer->value)->where('status', 'active')->count()],
            'events' => ['published' => Event::query()->where('status', EventStatus::Published->value)->count(), 'draft' => Event::query()->where('status', EventStatus::Draft->value)->count(), 'suspended' => Event::query()->where('status', EventStatus::Suspended->value)->count(), 'future' => Event::query()->where('starts_at', '>', now())->count()],
            'bookings' => ['valid' => (clone $valid)->count(), 'cancelled' => Booking::query()->whereIn('status', ['cancelled_by_customer', 'cancelled_by_organizer'])->count()],
            'participants' => (int) (clone $valid)->sum('total_people'), 'check_ins' => CheckIn::query()->count(),
            'economics' => ['billable_participants' => (int) (clone $valid)->sum('total_people'), 'unlimited_revenue_cents' => $unlimitedRevenue, 'pay_per_booking_revenue_cents' => $payPerBookingRevenue, 'theoretical_revenue_cents' => $unlimitedRevenue + $payPerBookingRevenue, 'criteria' => 'Accordi active: canone mensile UNLIMITED + partecipanti di booking confirmed/checked_in/completed moltiplicati per la tariffa PAY_PER_BOOKING.'],
        ];
    }
}
