<?php

namespace Tests\Feature;

use App\Actions\Bookings\CreateBooking;
use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\OrganizerProfile;
use App\Models\Province;
use App\Models\Region;
use App\Models\TicketType;
use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDOException;
use Tests\TestCase;

class BookingQrTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_persists_secure_qr_and_never_serializes_internal_fields(): void
    {
        [$customer, $event, $ticket] = $this->domain();

        $response = $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'secure-qr-create')
            ->postJson("/api/v1/events/{$event->ulid}/bookings", $this->payload($ticket))
            ->assertCreated()
            ->assertJsonMissingPath('data.qr_token_hash')
            ->assertJsonMissingPath('data.qr_token_encrypted');

        $token = $response->json('data.qr_token');
        $booking = $customer->bookings()->firstOrFail();
        $rawEncrypted = DB::table('bookings')->where('id', $booking->id)->value('qr_token_encrypted');

        $this->assertIsString($token);
        $this->assertGreaterThanOrEqual(64, strlen($token));
        $this->assertTrue(Hash::check($token, $booking->qr_token_hash));
        $this->assertSame($token, $booking->qr_token_encrypted);
        $this->assertIsString($rawEncrypted);
        $this->assertNotSame($token, $rawEncrypted);
    }

    public function test_owner_can_retrieve_qr_while_other_customer_is_forbidden(): void
    {
        [$customer, $event, $ticket] = $this->domain();
        $created = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/events/{$event->ulid}/bookings", $this->payload($ticket))
            ->assertCreated();
        $bookingUlid = $created->json('data.ulid');
        $token = $created->json('data.qr_token');

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/v1/me/bookings/{$bookingUlid}/qr")
            ->assertOk()
            ->assertJsonPath('data.booking_ulid', $bookingUlid)
            ->assertJsonPath('data.qr_token', $token)
            ->assertJsonMissingPath('data.qr_token_hash')
            ->assertJsonMissingPath('data.qr_token_encrypted');

        $other = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active']);
        $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/me/bookings/{$bookingUlid}/qr")
            ->assertForbidden();
    }

    public function test_qr_endpoint_requires_authentication_and_returns_not_found(): void
    {
        $this->getJson('/api/v1/me/bookings/01J00000000000000000000000/qr')->assertUnauthorized();

        $customer = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active']);
        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/me/bookings/01J00000000000000000000000/qr')
            ->assertNotFound();
    }

    public function test_legacy_booking_without_encrypted_token_returns_controlled_conflict(): void
    {
        [$customer, $event] = $this->domain();
        $booking = $customer->bookings()->create([
            'ulid' => (string) Str::ulid(),
            'event_id' => $event->id,
            'status' => 'confirmed',
            'total_people' => 1,
            'adults_count' => 1,
            'children_count' => 0,
            'booking_code' => 'EVT-LEGACY1',
            'qr_token_hash' => Hash::make(Str::random(64)),
            'booked_at' => now(),
        ]);

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/v1/me/bookings/{$booking->ulid}/qr")
            ->assertConflict()
            ->assertJsonPath('message', 'QR non disponibile per questa prenotazione.')
            ->assertJsonStructure(['message', 'errors', 'request_id']);
    }

    public function test_idempotent_retry_returns_same_booking_and_token_without_double_decrement(): void
    {
        [$customer, $event, $ticket] = $this->domain(capacity: 3);
        $payload = $this->payload($ticket, quantity: 2);

        $first = $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'same-secure-request')
            ->postJson("/api/v1/events/{$event->ulid}/bookings", $payload)
            ->assertCreated();
        $second = $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'same-secure-request')
            ->postJson("/api/v1/events/{$event->ulid}/bookings", $payload)
            ->assertCreated();

        $this->assertSame($first->json('data.ulid'), $second->json('data.ulid'));
        $this->assertSame($first->json('data.qr_token'), $second->json('data.qr_token'));
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'available_total' => 1]);
        $this->assertDatabaseHas('ticket_types', ['id' => $ticket->id, 'booked_quantity' => 2]);
    }

    public function test_unique_collision_recovers_winning_booking_without_exposing_sql_or_decrementing_again(): void
    {
        [$customer, $event, $ticket] = $this->domain(capacity: 3);
        $payload = $this->payload($ticket, quantity: 2);
        $key = 'concurrent-secure-request';

        $winner = app(CreateBooking::class)->handle($customer, $event, $payload, $key);
        $eventAvailability = $event->fresh()->available_total;
        $ticketAvailability = $ticket->fresh()->booked_quantity;

        $collision = new class extends CreateBooking
        {
            protected function transaction(Closure $callback): Booking
            {
                throw new QueryException(
                    'pgsql',
                    'insert into bookings (...) values (...)',
                    [],
                    new PDOException('duplicate key', 23505),
                );
            }
        };

        $recovered = $collision->handle($customer, $event, $payload, $key);

        $this->assertSame($winner->ulid, $recovered->ulid);
        $this->assertSame($winner->qr_token, $recovered->qr_token);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertSame($eventAvailability, $event->fresh()->available_total);
        $this->assertSame($ticketAvailability, $ticket->fresh()->booked_quantity);
    }

    /** @return array{User, Event, TicketType} */
    private function domain(int $capacity = 5): array
    {
        $customer = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active']);
        $organizer = User::factory()->create(['role' => UserRole::Organizer, 'status' => 'active']);
        $region = Region::query()->create(['name' => 'Molise QR', 'code' => 'MQR']);
        $province = Province::query()->create(['region_id' => $region->id, 'name' => 'Campobasso QR', 'code' => 'QR']);
        $municipality = Municipality::query()->create(['province_id' => $province->id, 'name' => 'Campobasso QR', 'istat_code' => 'QR0001']);
        $category = Category::query()->create(['name' => 'Categoria QR', 'slug' => 'categoria-qr', 'is_active' => true]);
        $profile = OrganizerProfile::query()->create([
            'ulid' => (string) Str::ulid(),
            'owner_user_id' => $organizer->id,
            'name' => 'Organizer QR',
            'type' => 'associazione',
            'email' => 'organizer-qr@example.test',
            'municipality_id' => $municipality->id,
            'verification_status' => 'verified',
        ]);
        $event = Event::query()->create([
            'ulid' => (string) Str::ulid(),
            'organizer_profile_id' => $profile->id,
            'municipality_id' => $municipality->id,
            'title' => 'Evento QR sicuro',
            'slug' => 'evento-qr-sicuro-'.Str::random(6),
            'summary' => 'Evento per test QR',
            'description' => 'Evento per test QR persistente.',
            'status' => EventStatus::Published,
            'starts_at' => now()->addWeek(),
            'capacity_total' => $capacity,
            'available_total' => $capacity,
            'is_free' => false,
            'family_friendly' => true,
            'indoor_outdoor' => 'mixed',
        ]);
        $event->categories()->attach($category);
        $ticket = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Ingresso',
            'audience' => 'generic',
            'max_quantity' => $capacity,
            'price_cents' => 1500,
            'is_active' => true,
        ]);

        return [$customer, $event, $ticket];
    }

    /** @return array<string, mixed> */
    private function payload(TicketType $ticket, int $quantity = 1): array
    {
        return [
            'adults_count' => $quantity,
            'children_count' => 0,
            'items' => [['ticket_type_ulid' => $ticket->ulid, 'quantity' => $quantity]],
        ];
    }
}
