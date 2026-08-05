<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\OrganizerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBusinessBackofficeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_is_real_and_admin_only(): void
    {
        User::factory()->create(['role' => UserRole::Customer, 'status' => 'active']);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/dashboard')->assertOk()
            ->assertJsonPath('data.customers.total', 1)->assertJsonPath('data.customers.active', 1)
            ->assertJsonStructure(['data' => ['organizers', 'events', 'bookings', 'participants', 'check_ins', 'economics' => ['theoretical_revenue_cents', 'criteria']]]);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Customer]));
        $this->getJson('/api/v1/admin/dashboard')->assertForbidden();
    }

    public function test_organizer_transitions_are_protected_and_customer_list_is_paginated(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create(['role' => UserRole::Organizer]);
        $organizer = OrganizerProfile::query()->create(['ulid' => (string) str()->ulid(), 'owner_user_id' => $owner->id, 'name' => 'Impresa Vera', 'type' => 'azienda', 'email' => $owner->email, 'verification_status' => 'verified']);
        User::factory()->create(['role' => UserRole::Customer, 'name' => 'Cliente Trovabile', 'status' => 'active']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/organizers?q=Impresa&status=verified')->assertOk()->assertJsonPath('data.total', 1);
        $this->postJson('/api/v1/admin/organizers/'.$organizer->ulid.'/suspend', ['reason' => 'Verifica amministrativa'])->assertOk()->assertJsonPath('data.verification_status', 'suspended');
        $this->postJson('/api/v1/admin/organizers/'.$organizer->ulid.'/suspend', ['reason' => 'Duplicata'])->assertConflict();
        $this->postJson('/api/v1/admin/organizers/'.$organizer->ulid.'/reactivate')->assertOk()->assertJsonPath('data.verification_status', 'verified');
        $this->getJson('/api/v1/admin/customers?q=Trovabile&per_page=10')->assertOk()->assertJsonPath('data.total', 1)->assertJsonMissingPath('data.data.0.password');
    }

    public function test_pay_per_booking_agreement_requires_custom_fee_and_records_history(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create(['role' => UserRole::Organizer]);
        $organizer = OrganizerProfile::query()->create(['ulid' => (string) str()->ulid(), 'owner_user_id' => $owner->id, 'name' => 'Organizer Business', 'type' => 'azienda', 'email' => $owner->email, 'verification_status' => 'verified']);
        Sanctum::actingAs($admin);
        $plan = $this->postJson('/api/v1/admin/plans', ['name' => 'PAY_PER_BOOKING', 'billing_model' => 'pay_per_booking', 'billing_interval' => 'monthly', 'monthly_price_cents' => 0, 'booking_fee_cents' => 0, 'is_active' => true])->assertCreated()->json('data');
        $url = '/api/v1/admin/organizers/'.$organizer->ulid.'/plan';
        $this->putJson($url, ['plan_ulid' => $plan['ulid'], 'status' => 'active'])->assertUnprocessable();
        $this->putJson($url, ['plan_ulid' => $plan['ulid'], 'status' => 'active', 'booking_fee_cents' => 125, 'agreement_starts_at' => '2026-08-01'])->assertOk()
            ->assertJsonPath('data.booking_fee_cents', 125)->assertJsonCount(1, 'data.history');
    }
}
