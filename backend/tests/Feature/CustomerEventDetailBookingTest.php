<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\OperationalMessage;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Municipality;
use App\Models\OrganizerProfile;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerEventDetailBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_event_is_listed_by_ulid_opened_booked_and_returns_qr(): void
    {
        Mail::fake();
        [$customer, $organizer, $municipality, $category] = $this->domain();

        $this->actingAs($customer, 'sanctum')->getJson('/api/v1/me/profile')
            ->assertOk()->assertJsonPath('data.phone', '+393331234567');

        $created = $this->actingAs($organizer, 'sanctum')->postJson('/api/v1/organizer/events', $this->eventPayload($municipality, $category, 'Evento creato Organizer'))
            ->assertCreated();
        $ulid = $created->json('data.ulid');
        $ticketUlid = $created->json('data.ticket_types.0.ulid');
        $this->postJson("/api/v1/organizer/events/{$ulid}/publish")->assertOk();

        $list = $this->actingAs($customer, 'sanctum')->getJson('/api/v1/events?available=1')
            ->assertOk()->assertJsonPath('data.0.ulid', $ulid)
            ->assertJsonPath('data.0.title', 'Evento creato Organizer');
        $this->assertNotEmpty($list->json('data.0.ulid'));

        $this->getJson("/api/v1/events/{$ulid}")
            ->assertOk()->assertJsonPath('data.ulid', $ulid)
            ->assertJsonPath('data.canonical_url', config('frontend.url').'/customer/events/'.$ulid)
            ->assertJsonPath('data.ticket_types.0.ulid', $ticketUlid)
            ->assertJsonMissingPath('data.ticket_types.0.id')
            ->assertJsonPath('data.ticket_types.0.name', 'Ingresso')
            ->assertJsonMissingPath('data.contact_phone');

        $payload = ['adults_count' => 1, 'children_count' => 0, 'items' => [['ticket_type_ulid' => $ticketUlid, 'quantity' => 1]]];
        $this->withHeader('Idempotency-Key', 'customer-web-missing-phone')
            ->postJson("/api/v1/events/{$ulid}/bookings", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('phone');

        $booking = $this->withHeader('Idempotency-Key', 'customer-web-detail-booking')
            ->postJson("/api/v1/events/{$ulid}/bookings", ['phone' => '+39 333-123.4567'] + $payload)
            ->assertCreated()->assertJsonPath('data.event.ulid', $ulid)
            ->assertJsonPath('data.contact_phone', '+393331234567');

        $bookingUlid = $booking->json('data.ulid');
        $qrToken = $booking->json('data.qr_token');
        $bookingCode = $booking->json('data.booking_code');
        $this->assertNotEmpty($qrToken);
        $this->assertDatabaseHas('bookings', [
            'ulid' => $bookingUlid,
            'user_id' => $customer->id,
            'contact_phone' => '+393331234567',
            'total_people' => 1,
            'status' => 'confirmed',
        ]);
        Mail::assertQueued(OperationalMessage::class, fn (OperationalMessage $mail): bool => $mail->hasTo($customer->email));

        $this->getJson("/api/v1/me/bookings/{$bookingUlid}/qr")
            ->assertOk()->assertJsonPath('data.qr_token', $qrToken);
        $this->getJson('/api/v1/me/bookings')
            ->assertOk()->assertJsonPath('data.0.ulid', $bookingUlid)
            ->assertJsonPath('data.0.contact_phone', '+393331234567');

        $this->actingAs($organizer, 'sanctum')->getJson("/api/v1/organizer/bookings/{$bookingUlid}")
            ->assertOk()->assertJsonPath('data.customer.phone', '+393331234567');
        $this->getJson('/api/v1/organizer/dashboard')
            ->assertOk()
            ->assertJsonPath('data.bi.bookings_received', 1)
            ->assertJsonPath('data.bi.participants_total', 1);

        $this->actingAs($customer, 'sanctum')->patchJson("/api/v1/me/bookings/{$bookingUlid}", [
            'phone' => '+39 320 7654321',
            'save_phone_to_profile' => true,
            'notes' => 'Arrivo dopo le 18',
            'adults_count' => 3,
            'children_count' => 0,
            'items' => [['ticket_type_ulid' => $ticketUlid, 'quantity' => 3]],
        ])->assertOk()
            ->assertJsonPath('data.ulid', $bookingUlid)
            ->assertJsonPath('data.booking_code', $bookingCode)
            ->assertJsonPath('data.total_people', 3)
            ->assertJsonPath('data.contact_phone', '+393207654321')
            ->assertJsonPath('data.notes', 'Arrivo dopo le 18');

        $this->assertDatabaseHas('users', ['id' => $customer->id, 'phone' => '+393207654321']);
        $this->assertDatabaseHas('events', ['ulid' => $ulid, 'available_total' => 7]);
        $this->getJson("/api/v1/me/bookings/{$bookingUlid}/qr")
            ->assertOk()->assertJsonPath('data.qr_token', $qrToken);
        $this->actingAs($organizer, 'sanctum')->getJson("/api/v1/organizer/bookings/{$bookingUlid}")
            ->assertOk()->assertJsonPath('data.customer.phone', '+393207654321')
            ->assertJsonPath('data.people', 3)->assertJsonPath('data.tickets.0.quantity', 3);
        $this->getJson('/api/v1/organizer/dashboard')
            ->assertOk()->assertJsonPath('data.bi.participants_total', 3);
        $this->getJson("/api/v1/organizer/events/{$ulid}/participants")
            ->assertOk()->assertJsonPath('data.0.phone', '+393207654321')
            ->assertJsonPath('data.0.tickets', 'Ingresso x 3')->assertJsonPath('summary.people_total', 3);
        $csv = $this->get("/api/v1/organizer/events/{$ulid}/participants?format=csv")->assertOk();
        $this->assertStringContainsString('Ingresso x 3', $csv->streamedContent());

        $overCapacityPayload = [
            'phone' => '+393207654321',
            'notes' => 'Il form deve conservare questi dati',
            'adults_count' => 50,
            'children_count' => 0,
            'items' => [['ticket_type_ulid' => $ticketUlid, 'quantity' => 50]],
        ];
        $this->actingAs($customer, 'sanctum')->patchJson("/api/v1/me/bookings/{$bookingUlid}", $overCapacityPayload)
            ->assertUnprocessable();
        $this->assertDatabaseHas('bookings', ['ulid' => $bookingUlid, 'total_people' => 3, 'contact_phone' => '+393207654321', 'notes' => 'Arrivo dopo le 18']);
        $this->assertDatabaseHas('events', ['ulid' => $ulid, 'available_total' => 7]);

        $otherCustomer = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active']);
        $this->actingAs($otherCustomer, 'sanctum')->patchJson("/api/v1/me/bookings/{$bookingUlid}", [
            'phone' => '+393331111111', 'adults_count' => 1, 'items' => [['ticket_type_ulid' => $ticketUlid, 'quantity' => 1]],
        ])->assertForbidden();

        Booking::query()->where('ulid', $bookingUlid)->update(['status' => 'checked_in']);
        $this->actingAs($customer, 'sanctum')->patchJson("/api/v1/me/bookings/{$bookingUlid}", [
            'phone' => '+393207654321', 'adults_count' => 3, 'items' => [['ticket_type_ulid' => $ticketUlid, 'quantity' => 3]],
        ])->assertUnprocessable();
    }

    public function test_customer_booking_edit_confirmation_is_wired_to_patch_and_prevents_duplicate_clicks(): void
    {
        $source = file_get_contents(base_path('../frontend/src/App.ts'));

        $this->assertIsString($source);
        $this->assertStringContainsString("phone: booking.contact_phone ?? customerProfile.value.phone ?? ''", $source);
        $this->assertStringContainsString('() => { void confirmCustomerBookingEdit(); })', $source);
        $this->assertStringContainsString('disabled: customerBookingLoading.value, onClick: onConfirm', $source);
        $this->assertStringContainsString("method: 'PATCH'", $source);
        $this->assertStringContainsString('customerBookingEdit.value = null;', $source);
        $this->assertStringContainsString("await loadCustomerSection('bookings');", $source);
    }

    public function test_unpublished_event_created_by_another_organizer_is_not_listed_or_accessible(): void
    {
        [$customer, $organizer, $municipality, $category] = $this->domain();
        $draft = $this->actingAs($organizer, 'sanctum')->postJson('/api/v1/organizer/events', $this->eventPayload($municipality, $category, 'Bozza privata'))
            ->assertCreated();
        $ulid = $draft->json('data.ulid');

        $this->actingAs($customer, 'sanctum')->getJson('/api/v1/events')->assertOk()->assertJsonMissing(['ulid' => $ulid]);
        $this->getJson("/api/v1/events/{$ulid}")->assertNotFound();
    }

    /** @return array{User, User, Municipality, Category} */
    private function domain(): array
    {
        $customer = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active', 'phone' => '+393331234567']);
        $organizer = User::factory()->create(['role' => UserRole::Organizer, 'status' => 'active']);
        $region = Region::query()->create(['name' => 'Molise Customer', 'code' => 'MCT']);
        $province = Province::query()->create(['region_id' => $region->id, 'name' => 'Campobasso', 'code' => 'CB']);
        $municipality = Municipality::query()->create(['province_id' => $province->id, 'name' => 'Campobasso', 'istat_code' => '070006', 'postal_code' => '86100']);
        OrganizerProfile::query()->create(['ulid' => (string) Str::ulid(), 'owner_user_id' => $organizer->id, 'name' => 'Organizer Customer QA', 'type' => 'associazione', 'email' => 'organizer-customer@example.test', 'municipality_id' => $municipality->id, 'verification_status' => 'verified', 'verified_at' => now()]);
        $category = Category::query()->create(['name' => 'Laboratori Customer', 'slug' => 'laboratori-customer', 'is_active' => true]);

        return [$customer, $organizer, $municipality, $category];
    }

    /** @return array<string, mixed> */
    private function eventPayload(Municipality $municipality, Category $category, string $title): array
    {
        return ['title' => $title, 'summary' => 'Evento pubblico prenotabile.', 'description' => 'Dettaglio completo per il Customer.', 'municipality_id' => $municipality->id, 'category_ids' => [$category->id], 'starts_at' => now()->addWeek()->toIso8601String(), 'capacity_total' => 10, 'is_free' => true, 'family_friendly' => false, 'indoor_outdoor' => 'indoor', 'ticket_types' => [['name' => 'Ingresso', 'audience' => 'generic', 'price_cents' => 0, 'max_quantity' => 10]]];
    }
}
