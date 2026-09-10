<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\OrganizerProfile;
use App\Models\Province;
use App\Models\Region;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostQaAdminFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_creates_normalized_unique_category_available_publicly(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);

        $this->actingAs($customer, 'sanctum')->postJson('/api/v1/admin/categories', ['name' => 'Nuova categoria'])->assertForbidden();
        $this->actingAs($organizer, 'sanctum')->postJson('/api/v1/admin/categories', ['name' => 'Nuova categoria'])->assertForbidden();
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/categories', ['name' => '  Sapori   Locali  '])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Sapori Locali')
            ->assertJsonPath('data.slug', 'sapori-locali');
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/categories', ['name' => 'sapori locali'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->getJson('/api/v1/categories')->assertOk()->assertJsonPath('data.0.slug', 'sapori-locali');
    }

    public function test_admin_event_detail_exposes_only_valid_booked_customers_and_non_admin_is_forbidden(): void
    {
        [$organizer, , $event] = $this->eventFixture();
        $validCustomer = User::factory()->create(['role' => UserRole::Customer, 'name' => 'Cliente valido']);
        $cancelledCustomer = User::factory()->create(['role' => UserRole::Customer, 'name' => 'Cliente annullato']);
        $valid = $this->booking($event, $validCustomer, 'confirmed', 'VALID-01');
        $this->booking($event, $cancelledCustomer, 'cancelled_by_customer', 'CANCELLED-01');
        TicketType::query()->create(['event_id' => $event->id, 'name' => 'Ingresso', 'audience' => 'generic', 'price_cents' => 1000, 'max_quantity' => 20, 'booked_quantity' => 2, 'is_active' => true]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin, 'sanctum')->getJson("/api/v1/admin/events/{$event->ulid}")
            ->assertOk()
            ->assertJsonPath('data.organizer_profile.owner.email', $organizer->email)
            ->assertJsonPath('data.bookings_count', 1)
            ->assertJsonPath('data.bookings.0.ulid', $valid->ulid)
            ->assertJsonPath('data.bookings.0.user.name', 'Cliente valido')
            ->assertJsonMissing(['booking_code' => 'CANCELLED-01'])
            ->assertJsonMissingPath('data.bookings.0.notes');

        $this->actingAs($validCustomer, 'sanctum')->getJson("/api/v1/admin/events/{$event->ulid}")->assertForbidden();
        $this->actingAs($organizer, 'sanctum')->getJson("/api/v1/admin/events/{$event->ulid}")->assertForbidden();
    }

    /** @return array{User, OrganizerProfile, Event} */
    private function eventFixture(): array
    {
        $region = Region::query()->create(['name' => 'Molise', 'code' => 'MOL']);
        $province = Province::query()->create(['region_id' => $region->id, 'name' => 'Campobasso', 'code' => 'CB']);
        $municipality = Municipality::query()->create(['province_id' => $province->id, 'name' => 'Campobasso', 'istat_code' => '070006']);
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);
        $profile = OrganizerProfile::query()->create(['ulid' => (string) Str::ulid(), 'owner_user_id' => $organizer->id, 'name' => 'Impresa', 'type' => 'azienda', 'email' => $organizer->email, 'municipality_id' => $municipality->id, 'verification_status' => 'verified', 'verified_at' => now()]);
        $event = Event::query()->create(['ulid' => (string) Str::ulid(), 'organizer_profile_id' => $profile->id, 'municipality_id' => $municipality->id, 'title' => 'Evento Admin', 'slug' => 'evento-admin', 'summary' => 'Riepilogo', 'description' => 'Descrizione', 'status' => EventStatus::Published, 'starts_at' => now()->addWeek(), 'capacity_total' => 20, 'available_total' => 18, 'is_free' => false, 'family_friendly' => false, 'indoor_outdoor' => 'indoor']);

        return [$organizer, $profile, $event];
    }

    private function booking(Event $event, User $customer, string $status, string $code): Booking
    {
        return Booking::query()->create(['ulid' => (string) Str::ulid(), 'user_id' => $customer->id, 'event_id' => $event->id, 'status' => $status, 'total_people' => 2, 'adults_count' => 2, 'children_count' => 0, 'booking_code' => $code, 'qr_token_hash' => hash('sha256', $code), 'booked_at' => now(), 'event_terms_accepted' => true, 'notes' => 'Dato non necessario']);
    }
}
