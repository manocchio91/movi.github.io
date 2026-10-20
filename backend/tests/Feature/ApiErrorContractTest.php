<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\OrganizerProfile;
use App\Models\Province;
use App\Models\Region;
use App\Models\Review;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Contratto uniforme degli errori API e completezza dell'audit log.
 */
class ApiErrorContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_rule_422_keeps_its_specific_message(): void
    {
        [$customer, , $event] = $this->setupDomain();
        $event->forceFill(['status' => EventStatus::Draft])->save();
        $ticket = TicketType::query()->create([
            'ulid' => (string) Str::ulid(),
            'event_id' => $event->id,
            'name' => 'Adulto',
            'audience' => 'adult',
            'max_quantity' => 10,
            'price_cents' => 1000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/events/{$event->ulid}/bookings", [
                'adults_count' => 1,
                'children_count' => 0,
                'phone' => '+393331234567',
                'items' => [['ticket_type_ulid' => $ticket->ulid, 'quantity' => 1]],
            ]);

        $response->assertStatus(422);
        // La regola di business "Evento non prenotabile." deve arrivare al cliente.
        $response->assertJsonPath('message', 'Evento non prenotabile.');
        $response->assertJsonStructure(['message', 'errors', 'request_id']);
    }

    public function test_validation_422_keeps_field_level_errors(): void
    {
        [$customer, , $event] = $this->setupDomain();

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/events/{$event->ulid}/bookings", [
                'adults_count' => 1,
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'I dati forniti non sono validi.');
        $response->assertJsonStructure(['message', 'errors', 'request_id']);
        $this->assertNotEmpty($response->json('errors'));
    }

    public function test_error_envelope_is_complete_for_unauthenticated_requests(): void
    {
        $response = $this->getJson('/api/v1/me/bookings');

        $response->assertStatus(401);
        $response->assertJsonStructure(['message', 'errors', 'request_id']);
    }

    public function test_organizer_review_actions_are_audited_without_duplicates(): void
    {
        [$customer, $organizer, $event] = $this->setupDomain();
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);
        // La revisione e' ammessa solo su un profilo in attesa di revisione.
        $organizer->organizerProfile->forceFill([
            'verification_status' => 'pending',
            'verified_at' => null,
        ])->save();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/organizers/{$organizer->organizerProfile->ulid}/approve")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.organizer.approved',
            'subject_type' => OrganizerProfile::class,
            'subject_id' => $organizer->organizerProfile->id,
        ]);

        // Una seconda approvazione non deve creare una riga duplicata.
        // La regola di business rifiuta la revisione su un profilo gia' approvato.
        $before = AuditLog::query()->count();
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/organizers/{$organizer->organizerProfile->ulid}/approve")
            ->assertStatus(409);
        $this->assertSame($before, AuditLog::query()->count());
    }

    public function test_event_moderation_actions_are_audited(): void
    {
        [$customer, , $event] = $this->setupDomain();
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/events/{$event->ulid}/suspend")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.event.suspended',
            'subject_type' => Event::class,
            'subject_id' => $event->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/events/{$event->ulid}/archive")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.event.archived',
            'subject_type' => Event::class,
            'subject_id' => $event->id,
        ]);
    }

    public function test_review_moderation_actions_are_audited(): void
    {
        [$customer, , $event] = $this->setupDomain();
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => 'active']);
        $review = Review::query()->create([
            'ulid' => (string) Str::ulid(),
            'event_id' => $event->id,
            'organizer_profile_id' => $event->organizer_profile_id,
            'user_id' => $customer->id,
            'rating' => 4,
            'would_recommend' => true,
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/reviews/{$review->ulid}/approve")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.review.approved',
            'subject_type' => Review::class,
            'subject_id' => $review->id,
        ]);
    }

    private function setupDomain(int $capacity = 10): array
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
        $region = Region::query()->create(['name' => 'Molise '.Str::random(4), 'code' => Str::upper(Str::random(3))]);
        config(['territory.region_code' => $region->code]);
        $province = Province::query()->create(['region_id' => $region->id, 'name' => 'Campobasso', 'code' => Str::upper(Str::random(2))]);
        $municipality = Municipality::query()->create(['province_id' => $province->id, 'name' => 'Campobasso', 'istat_code' => Str::random(6)]);
        $category = Category::query()->create(['name' => 'bambini '.Str::random(4), 'slug' => Str::slug('bambini '.Str::random(6)), 'is_active' => true]);

        $profile = OrganizerProfile::query()->create([
            'ulid' => (string) Str::ulid(),
            'owner_user_id' => $organizer->id,
            'name' => 'Locale Demo',
            'type' => 'ristorante',
            'email' => 'locale@example.test',
            'municipality_id' => $municipality->id,
            'verification_status' => 'verified',
        ]);

        $event = Event::query()->create([
            'ulid' => (string) Str::ulid(),
            'organizer_profile_id' => $profile->id,
            'municipality_id' => $municipality->id,
            'title' => 'Evento test',
            'slug' => 'evento-test-'.Str::random(6),
            'summary' => 'Evento di test',
            'description' => 'Descrizione evento di test',
            'status' => EventStatus::Published,
            'starts_at' => now()->addWeek(),
            'capacity_total' => $capacity,
            'available_total' => $capacity,
            'is_free' => false,
            'price_notes' => 'Pagamento sul posto',
            'family_friendly' => true,
            'indoor_outdoor' => 'mixed',
        ]);

        $event->categories()->sync([$category->id]);

        return [$customer, $organizer, $event, collect(['bambini' => $category]), $municipality];
    }
}
