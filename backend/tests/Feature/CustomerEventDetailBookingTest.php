<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Municipality;
use App\Models\OrganizerProfile;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerEventDetailBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_event_is_listed_by_ulid_opened_booked_and_returns_qr(): void
    {
        [$customer, $organizer, $municipality, $category] = $this->domain();

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
            ->assertJsonPath('data.ticket_types.0.ulid', $ticketUlid)
            ->assertJsonMissingPath('data.ticket_types.0.id')
            ->assertJsonPath('data.ticket_types.0.name', 'Ingresso');

        $booking = $this->withHeader('Idempotency-Key', 'customer-web-detail-booking')
            ->postJson("/api/v1/events/{$ulid}/bookings", ['adults_count' => 1, 'children_count' => 0, 'items' => [['ticket_type_ulid' => $ticketUlid, 'quantity' => 1]]])
            ->assertCreated()->assertJsonPath('data.event.ulid', $ulid);

        $bookingUlid = $booking->json('data.ulid');
        $qrToken = $booking->json('data.qr_token');
        $this->assertNotEmpty($qrToken);
        $this->getJson("/api/v1/me/bookings/{$bookingUlid}/qr")
            ->assertOk()->assertJsonPath('data.qr_token', $qrToken);
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
        $customer = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active']);
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
