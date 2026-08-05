<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\OrganizerProfile;
use App\Models\Province;
use App\Models\Region;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiErrorAndCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_errors_have_the_required_contract_and_request_id_header(): void
    {
        $this->withHeader('X-Request-ID', 'qa-request-id')->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertHeader('X-Request-ID', 'qa-request-id')
            ->assertJsonStructure(['message', 'errors', 'request_id'])
            ->assertJsonPath('request_id', 'qa-request-id');
    }

    public function test_validation_errors_have_the_required_contract(): void
    {
        $this->postJson('/api/v1/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors', 'request_id']);
    }

    public function test_internal_errors_never_expose_exception_or_sql_details_even_with_debug_enabled(): void
    {
        config()->set('app.debug', true);
        Route::get('/api/v1/testing/internal-error', fn () => throw new \RuntimeException('SQLSTATE secret_table stack trace'));

        $this->getJson('/api/v1/testing/internal-error')->assertInternalServerError()
            ->assertJsonPath('message', 'Errore interno del server.')
            ->assertJsonMissing(['SQLSTATE secret_table stack trace'])
            ->assertJsonStructure(['message', 'errors', 'request_id']);
    }

    public function test_booking_qr_can_be_checked_in_only_once_by_its_organizer(): void
    {
        [$customer, $organizer, $event, $ticket] = $this->domain();

        $response = $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'qr-check-in')
            ->postJson("/api/v1/events/{$event->ulid}/bookings", [
                'phone' => '+393331234567',
                'adults_count' => 1,
                'children_count' => 0,
                'items' => [['ticket_type_ulid' => $ticket->ulid, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->assertJsonMissingPath('data.qr_token_hash');

        $token = $response->json('data.qr_token');
        $this->assertIsString($token);

        $this->actingAs($organizer, 'sanctum')
            ->postJson("/api/v1/organizer/events/{$event->ulid}/check-ins", ['qr_token' => $token])
            ->assertCreated()
            ->assertJsonPath('data.booking_ulid', $response->json('data.ulid'))
            ->assertJsonPath('data.booking_status', 'checked_in')
            ->assertJsonMissingPath('data.booking')
            ->assertJsonMissingPath('data.id');

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/me/bookings/'.$response->json('data.ulid').'/qr')
            ->assertOk()
            ->assertJsonPath('data.qr_token', $token);

        $this->actingAs($organizer, 'sanctum')
            ->postJson("/api/v1/organizer/events/{$event->ulid}/check-ins", ['qr_token' => $token])
            ->assertConflict()
            ->assertJsonStructure(['message', 'errors', 'request_id']);
    }

    private function domain(): array
    {
        $customer = User::factory()->create([
            'role' => UserRole::Customer,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $organizer = User::factory()->create([
            'role' => UserRole::Organizer,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $region = Region::query()->create(['name' => 'Molise', 'code' => 'MOL']);
        $province = Province::query()->create(['region_id' => $region->id, 'name' => 'Campobasso', 'code' => 'CB']);
        $municipality = Municipality::query()->create(['province_id' => $province->id, 'name' => 'Campobasso', 'istat_code' => '070006']);
        $profile = OrganizerProfile::query()->create([
            'ulid' => (string) Str::ulid(), 'owner_user_id' => $organizer->id,
            'name' => 'Organizer QA', 'type' => 'associazione',
            'email' => 'organizer-qa@example.test', 'municipality_id' => $municipality->id,
            'verification_status' => 'verified', 'verified_at' => now(),
        ]);
        $event = Event::query()->create([
            'ulid' => (string) Str::ulid(), 'organizer_profile_id' => $profile->id,
            'municipality_id' => $municipality->id, 'title' => 'Evento QR', 'slug' => 'evento-qr',
            'summary' => 'Evento per test QR.', 'description' => 'Evento per verificare il check-in monouso.',
            'status' => EventStatus::Published, 'starts_at' => now()->addDay(),
            'capacity_total' => 10, 'available_total' => 10, 'is_free' => true,
            'family_friendly' => false, 'indoor_outdoor' => 'indoor',
        ]);
        $ticket = TicketType::query()->create([
            'event_id' => $event->id, 'name' => 'Ingresso', 'audience' => 'generic',
            'max_quantity' => 10, 'price_cents' => 0, 'is_active' => true,
        ]);

        return [$customer, $organizer, $event, $ticket];
    }
}
