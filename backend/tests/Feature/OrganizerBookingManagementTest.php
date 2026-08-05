<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\OrganizerProfile;
use App\Models\Province;
use App\Models\Region;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrganizerBookingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_can_search_filter_sort_and_paginate_only_own_bookings(): void
    {
        [$organizer, $event, $ticket] = $this->domain('Evento del borgo');
        [, $otherEvent, $otherTicket] = $this->domain('Evento esterno');
        $owned = $this->booking($event, $ticket, 'confirmed', 'Mario Rossi', 'mario@example.test', 3);
        $this->booking($event, $ticket, 'completed', 'Anna Verdi', 'anna@example.test');
        $foreign = $this->booking($otherEvent, $otherTicket, 'confirmed', 'Mario Esterno', 'outside@example.test');

        $response = $this->actingAs($organizer, 'sanctum')->getJson('/api/v1/organizer/bookings?search=Mario&filter=confirmed&sort_by=people&sort=desc&per_page=1');

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ulid', $owned->ulid)->assertJsonPath('meta.total', 1)
            ->assertJsonMissing(['ulid' => $foreign->ulid])->assertJsonMissingPath('data.0.qr_token');
    }

    public function test_detail_contains_required_data_and_enforces_ownership_with_admin_override(): void
    {
        [$organizer, $event, $ticket] = $this->domain('Laboratorio');
        [$otherOrganizer] = $this->domain('Altro evento');
        $booking = $this->booking($event, $ticket, 'checked_in', 'Luca Blu', 'luca@example.test', notes: 'Arrivo alle 18');
        $booking->checkIns()->create(['operator_user_id' => $organizer->id, 'checked_in_at' => now()]);

        $this->actingAs($organizer, 'sanctum')->getJson("/api/v1/organizer/bookings/{$booking->ulid}")
            ->assertOk()->assertJsonPath('data.customer.email', 'luca@example.test')
            ->assertJsonPath('data.customer.phone', '+393331234567')
            ->assertJsonPath('data.event.title', 'Laboratorio')->assertJsonPath('data.tickets.0.name', 'Ingresso')
            ->assertJsonPath('data.notes', 'Arrivo alle 18')->assertJsonPath('data.qr_token', 'qr-secret');

        $this->actingAs($otherOrganizer, 'sanctum')->getJson("/api/v1/organizer/bookings/{$booking->ulid}")->assertForbidden();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $this->actingAs($customer, 'sanctum')->getJson("/api/v1/organizer/bookings/{$booking->ulid}")->assertForbidden();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin, 'sanctum')->getJson("/api/v1/organizer/bookings/{$booking->ulid}")
            ->assertOk()->assertJsonPath('data.customer.phone', '+393331234567');
    }

    public function test_validation_and_authentication_are_enforced(): void
    {
        $this->getJson('/api/v1/organizer/bookings')->assertUnauthorized();
        [$organizer] = $this->domain('Evento');
        $this->actingAs($organizer, 'sanctum')->getJson('/api/v1/organizer/bookings?filter=unknown&sort_by=id&per_page=100')
            ->assertUnprocessable()->assertJsonValidationErrors(['filter', 'sort_by', 'per_page']);
    }

    public function test_event_participants_are_paginated_counted_filtered_and_support_manual_check_in(): void
    {
        [$organizer, $event, $ticket] = $this->domain('Evento partecipanti');
        $pending = $this->booking($event, $ticket, 'confirmed', 'Mario Partecipante', 'mario.part@example.test', 3);
        $cancelled = $this->booking($event, $ticket, 'cancelled_by_customer', 'Cliente Annullato', 'cancelled@example.test', 4);

        $this->actingAs($organizer, 'sanctum')->getJson("/api/v1/organizer/events/{$event->ulid}/participants?search=Mario&filter=pending&per_page=1")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.booking_ulid', $pending->ulid)
            ->assertJsonPath('data.0.phone', '+393331234567')
            ->assertJsonPath('summary.bookings_total', 1)->assertJsonPath('summary.people_total', 3)
            ->assertJsonPath('summary.checked_in_total', 0)->assertJsonMissing(['booking_ulid' => $cancelled->ulid]);

        $this->postJson("/api/v1/organizer/events/{$event->ulid}/check-ins", ['booking_code' => $pending->booking_code])
            ->assertCreated()->assertJsonPath('data.booking_status', 'checked_in');
        $this->postJson("/api/v1/organizer/events/{$event->ulid}/check-ins", ['booking_code' => $pending->booking_code])->assertConflict();
        $this->postJson("/api/v1/organizer/events/{$event->ulid}/check-ins", ['booking_code' => $cancelled->booking_code])->assertConflict();

        $this->getJson("/api/v1/organizer/events/{$event->ulid}/participants?filter=checked_in")
            ->assertOk()->assertJsonPath('summary.checked_in_total', 3)->assertJsonPath('data.0.checked_in', true);
        $csv = $this->get("/api/v1/organizer/events/{$event->ulid}/participants?format=csv")
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Telefono', $csv->streamedContent());
        $this->assertStringContainsString('+393331234567', $csv->streamedContent());
    }

    /** @return array{User, Event, TicketType} */
    private function domain(string $title): array
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer, 'status' => 'active']);
        $region = Region::query()->create(['name' => 'Regione '.Str::random(5), 'code' => Str::upper(Str::random(3))]);
        $province = Province::query()->create(['region_id' => $region->id, 'name' => 'Provincia', 'code' => Str::upper(Str::random(2))]);
        $municipality = Municipality::query()->create(['province_id' => $province->id, 'name' => 'Comune', 'istat_code' => Str::random(6)]);
        $profile = OrganizerProfile::query()->create(['ulid' => (string) Str::ulid(), 'owner_user_id' => $organizer->id, 'name' => 'Organizer', 'type' => 'associazione', 'email' => Str::random(8).'@example.test', 'municipality_id' => $municipality->id, 'verification_status' => 'verified', 'verified_at' => now()]);
        $event = Event::query()->create(['ulid' => (string) Str::ulid(), 'organizer_profile_id' => $profile->id, 'municipality_id' => $municipality->id, 'title' => $title, 'slug' => Str::slug($title).'-'.Str::random(5), 'summary' => 'Sommario evento.', 'description' => 'Descrizione evento.', 'status' => EventStatus::Published, 'starts_at' => now()->addDay(), 'capacity_total' => 20, 'available_total' => 10, 'is_free' => true, 'family_friendly' => false, 'indoor_outdoor' => 'mixed']);
        $ticket = TicketType::query()->create(['event_id' => $event->id, 'name' => 'Ingresso', 'audience' => 'generic', 'max_quantity' => 20, 'price_cents' => 0, 'is_active' => true]);

        return [$organizer, $event, $ticket];
    }

    private function booking(Event $event, TicketType $ticket, string $status, string $name, string $email, int $people = 1, ?string $notes = null): Booking
    {
        $customer = User::factory()->create(['name' => $name, 'email' => $email, 'role' => UserRole::Customer]);
        $booking = Booking::query()->create(['ulid' => (string) Str::ulid(), 'user_id' => $customer->id, 'event_id' => $event->id, 'status' => $status, 'total_people' => $people, 'adults_count' => $people, 'booking_code' => 'BK-'.Str::upper(Str::random(8)), 'contact_phone' => '+393331234567', 'qr_token_hash' => Hash::make('qr-secret'.Str::random()), 'qr_token_encrypted' => 'qr-secret', 'booked_at' => now(), 'notes' => $notes]);
        BookingItem::query()->create(['booking_id' => $booking->id, 'ticket_type_id' => $ticket->id, 'quantity' => $people]);

        return $booking;
    }
}
