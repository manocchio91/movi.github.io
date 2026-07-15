<?php

namespace Tests\Feature;

use App\Actions\Notifications\NotifyInterestedCustomers;
use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\NotificationPreference;
use App\Models\OrganizerProfile;
use App\Models\Province;
use App\Models\Region;
use App\Models\SubscriptionPlan;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_saves_and_removes_multiple_category_preferences(): void
    {
        [$customer, , , $categories] = $this->setupDomain();

        $this->actingAs($customer, 'sanctum')
            ->putJson('/api/v1/me/preferences', ['category_ids' => $categories->take(2)->pluck('id')->all()])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($customer, 'sanctum')
            ->putJson('/api/v1/me/preferences', ['category_ids' => [$categories->first()->id]])
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_event_publication_notifies_only_interested_promotional_users(): void
    {
        [$customer, $organizer, $event, $categories] = $this->setupDomain();
        $dinnerOnly = User::factory()->create(['role' => UserRole::Customer]);
        $noPromo = User::factory()->create(['role' => UserRole::Customer]);

        $customer->preferredCategories()->sync([$categories['bambini']->id]);
        $dinnerOnly->preferredCategories()->sync([$categories['cene']->id]);
        $noPromo->preferredCategories()->sync([$categories['bambini']->id]);

        NotificationPreference::query()->create(['user_id' => $customer->id, 'internal_enabled' => true, 'email_enabled' => true, 'promotional_enabled' => true, 'frequency' => 'immediate']);
        NotificationPreference::query()->create(['user_id' => $dinnerOnly->id, 'internal_enabled' => true, 'email_enabled' => true, 'promotional_enabled' => true, 'frequency' => 'immediate']);
        NotificationPreference::query()->create(['user_id' => $noPromo->id, 'internal_enabled' => true, 'email_enabled' => true, 'promotional_enabled' => false, 'frequency' => 'immediate']);

        $event->categories()->sync([$categories['bambini']->id]);

        app(NotifyInterestedCustomers::class)->handle($event);

        $this->assertDatabaseHas('notifications', ['user_id' => $customer->id, 'event_id' => $event->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $dinnerOnly->id, 'event_id' => $event->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $noPromo->id, 'event_id' => $event->id]);
        $this->assertSame($organizer->id, $event->organizerProfile->owner_user_id);
    }

    public function test_followers_receive_notification_even_without_matching_category(): void
    {
        [$customer, , $event, $categories] = $this->setupDomain();
        $customer->preferredCategories()->sync([$categories['cene']->id]);
        $customer->followedOrganizers()->syncWithoutDetaching([$event->organizer_profile_id]);

        NotificationPreference::query()->create([
            'user_id' => $customer->id,
            'internal_enabled' => true,
            'email_enabled' => false,
            'promotional_enabled' => true,
            'frequency' => 'immediate',
        ]);

        $event->categories()->sync([$categories['bambini']->id]);

        app(NotifyInterestedCustomers::class)->handle($event);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'channel' => 'internal',
        ]);
    }

    public function test_subscription_plans_are_exposed_from_database(): void
    {
        SubscriptionPlan::query()->create([
            'ulid' => (string) Str::ulid(),
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'Esperienze illimitate',
            'monthly_price_cents' => 1900,
            'is_public' => true,
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/subscription-plans')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'starter');
    }

    public function test_organizer_opportunity_engine_reports_competing_events(): void
    {
        [, $organizer, $event, $categories, $municipality] = $this->setupDomain();

        $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/v1/organizer/opportunities?starts_at='.$event->starts_at->toIso8601String().'&municipality_id='.$municipality->id.'&category_ids[]='.$categories['bambini']->id)
            ->assertOk()
            ->assertJsonPath('data.summary.events_same_day', 1)
            ->assertJsonPath('data.summary.similar_category_events', 1);
    }

    public function test_organizer_can_create_tracking_campaign(): void
    {
        [, $organizer, $event] = $this->setupDomain();

        $this->actingAs($organizer, 'sanctum')
            ->postJson('/api/v1/organizer/campaigns', [
                'event_id' => $event->id,
                'name' => 'Campagna Cena',
                'description' => 'Tracciamento campagna demo',
                'starts_at' => now()->toIso8601String(),
                'ends_at' => now()->addWeek()->toIso8601String(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Campagna Cena');

        $this->assertDatabaseHas('campaign_statistics', ['views' => 0, 'clicks' => 0, 'bookings_generated' => 0]);
    }

    public function test_customer_books_event_and_duplicate_idempotency_does_not_create_second_booking(): void
    {
        [$customer, , $event] = $this->setupDomain(capacity: 2);
        $ticket = TicketType::query()->create(['event_id' => $event->id, 'name' => 'Ingresso', 'audience' => 'generic', 'max_quantity' => 2, 'price_cents' => 1000, 'is_active' => true]);

        $payload = [
            'adults_count' => 2,
            'children_count' => 0,
            'items' => [['ticket_type_id' => $ticket->id, 'quantity' => 2]],
        ];

        $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'same-request')
            ->postJson("/api/v1/events/{$event->ulid}/bookings", $payload)
            ->assertCreated()
            ->assertJsonPath('data.total_people', 2);

        $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'same-request')
            ->postJson("/api/v1/events/{$event->ulid}/bookings", $payload)
            ->assertCreated();

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'available_total' => 0, 'status' => 'sold_out']);
    }

    public function test_cancelled_booking_releases_places_and_private_booking_is_protected(): void
    {
        [$customer, , $event] = $this->setupDomain(capacity: 3);
        $other = User::factory()->create(['role' => UserRole::Customer]);
        $ticket = TicketType::query()->create(['event_id' => $event->id, 'name' => 'Ingresso', 'audience' => 'generic', 'max_quantity' => 3, 'price_cents' => 0, 'is_active' => true]);

        $booking = Booking::query()->create([
            'ulid' => (string) Str::ulid(),
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'status' => 'confirmed',
            'total_people' => 2,
            'adults_count' => 2,
            'children_count' => 0,
            'booking_code' => 'EVT-TEST01',
            'qr_token_hash' => 'hash',
            'booked_at' => now(),
        ]);
        $booking->items()->create(['ticket_type_id' => $ticket->id, 'quantity' => 2]);
        $event->forceFill(['available_total' => 1])->save();

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/me/bookings/{$booking->ulid}")
            ->assertForbidden();

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/me/bookings/{$booking->ulid}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled_by_customer');

        $this->assertDatabaseHas('events', ['id' => $event->id, 'available_total' => 3]);
    }

    public function test_organizer_can_create_event_and_cannot_modify_others_events(): void
    {
        [, $organizer, $event, $categories, $municipality] = $this->setupDomain();
        $otherOrganizer = User::factory()->create(['role' => UserRole::Organizer]);

        $this->actingAs($organizer, 'sanctum')
            ->postJson('/api/v1/organizer/events', [
                'title' => 'Cena messicana',
                'summary' => 'Cena a tema con musica.',
                'description' => 'Evento gastronomico con posti limitati.',
                'municipality_id' => $municipality->id,
                'category_ids' => [$categories['cene']->id],
                'starts_at' => now()->addMonth()->toIso8601String(),
                'capacity_total' => 20,
                'is_free' => false,
                'family_friendly' => false,
                'indoor_outdoor' => 'indoor',
                'ticket_types' => [['name' => 'Persona', 'audience' => 'generic', 'price_cents' => 2500, 'max_quantity' => 20]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $this->actingAs($otherOrganizer, 'sanctum')
            ->putJson("/api/v1/organizer/events/{$event->ulid}", [
                'title' => 'Tentativo',
                'summary' => 'Tentativo modifica',
                'description' => 'Tentativo modifica evento altrui.',
                'municipality_id' => $municipality->id,
                'category_ids' => [$categories['cene']->id],
                'starts_at' => now()->addMonth()->toIso8601String(),
                'capacity_total' => 20,
                'is_free' => false,
                'family_friendly' => false,
                'indoor_outdoor' => 'indoor',
            ])
            ->assertForbidden();
    }

    public function test_unpublished_event_does_not_appear_publicly(): void
    {
        [, , $event] = $this->setupDomain();
        $event->forceFill(['status' => EventStatus::PendingReview])->save();

        $this->getJson('/api/v1/events')->assertOk()->assertJsonMissing(['ulid' => $event->ulid]);
    }

    private function setupDomain(int $capacity = 10): array
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);
        $region = Region::query()->create(['name' => 'Molise '.Str::random(4), 'code' => Str::upper(Str::random(3))]);
        $province = Province::query()->create(['region_id' => $region->id, 'name' => 'Campobasso', 'code' => Str::upper(Str::random(2))]);
        $municipality = Municipality::query()->create(['province_id' => $province->id, 'name' => 'Campobasso', 'istat_code' => Str::random(6)]);
        $categories = collect([
            'bambini' => Category::query()->create(['name' => 'bambini '.Str::random(4), 'slug' => Str::slug('bambini '.Str::random(6)), 'is_active' => true]),
            'cene' => Category::query()->create(['name' => 'cene '.Str::random(4), 'slug' => Str::slug('cene '.Str::random(6)), 'is_active' => true]),
        ]);

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

        $event->categories()->sync([$categories['bambini']->id]);

        return [$customer, $organizer, $event, $categories, $municipality];
    }
}
