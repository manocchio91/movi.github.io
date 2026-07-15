<?php

namespace Tests\Feature;

use App\Actions\Notifications\NotifyInterestedCustomers;
use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\EventSuccessScore;
use App\Models\Municipality;
use App\Models\NotificationPreference;
use App\Models\OrganizerProfile;
use App\Models\Province;
use App\Models\Region;
use App\Models\Review;
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

    public function test_login_returns_role_and_dashboard_redirect(): void
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $organizer = User::factory()->create(['role' => UserRole::Organizer]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->postJson('/api/v1/auth/login', ['email' => $customer->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.role', 'customer')
            ->assertJsonPath('redirect_to', '/customer/dashboard');

        $this->postJson('/api/v1/auth/login', ['email' => $organizer->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.role', 'organizer')
            ->assertJsonPath('redirect_to', '/organizer/dashboard');

        $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('redirect_to', '/admin/dashboard');
    }

    public function test_role_middleware_blocks_cross_role_api_access(): void
    {
        [$customer, $organizer] = $this->setupDomain();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/organizer/dashboard')
            ->assertForbidden();

        $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/v1/admin/dashboard')
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/customer/dashboard')
            ->assertForbidden();
    }

    public function test_customer_cannot_create_events_and_organizer_sees_only_own_events(): void
    {
        [$customer, $organizer, $event, $categories, $municipality] = $this->setupDomain();
        $otherOrganizer = User::factory()->create(['role' => UserRole::Organizer]);
        $otherProfile = OrganizerProfile::query()->create([
            'ulid' => (string) Str::ulid(),
            'owner_user_id' => $otherOrganizer->id,
            'name' => 'Altro Locale',
            'type' => 'associazione',
            'email' => 'altro@example.test',
            'municipality_id' => $municipality->id,
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);
        $otherEvent = Event::query()->create([
            'ulid' => (string) Str::ulid(),
            'organizer_profile_id' => $otherProfile->id,
            'municipality_id' => $municipality->id,
            'title' => 'Evento altro organizer',
            'slug' => 'evento-altro-'.Str::random(6),
            'summary' => 'Evento privato di altro organizer',
            'description' => 'Non deve essere visibile nella dashboard del primo organizer.',
            'status' => EventStatus::Published,
            'starts_at' => now()->addWeeks(3),
            'capacity_total' => 10,
            'available_total' => 10,
            'is_free' => true,
            'family_friendly' => false,
            'indoor_outdoor' => 'indoor',
        ]);

        $payload = [
            'title' => 'Evento cliente',
            'summary' => 'Tentativo cliente',
            'description' => 'Un cliente non puo creare eventi.',
            'municipality_id' => $municipality->id,
            'category_ids' => [$categories['bambini']->id],
            'starts_at' => now()->addMonth()->toIso8601String(),
            'capacity_total' => 12,
            'is_free' => true,
            'family_friendly' => true,
            'indoor_outdoor' => 'mixed',
        ];

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/organizer/events', $payload)
            ->assertForbidden();

        $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/v1/organizer/events')
            ->assertOk()
            ->assertJsonFragment(['ulid' => $event->ulid])
            ->assertJsonMissing(['ulid' => $otherEvent->ulid]);
    }

    public function test_unapproved_organizer_cannot_use_approved_area_or_publish(): void
    {
        [, $organizer, $event] = $this->setupDomain();
        $organizer->organizerProfile->forceFill(['verification_status' => 'pending', 'verified_at' => null])->save();

        $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/v1/organizer/profile')
            ->assertOk();

        $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/v1/organizer/dashboard')
            ->assertForbidden();

        $event->forceFill(['status' => EventStatus::Draft])->save();

        $this->actingAs($organizer, 'sanctum')
            ->postJson("/api/v1/organizer/events/{$event->ulid}/publish")
            ->assertForbidden();
    }

    public function test_organizer_sees_only_reviews_for_own_events(): void
    {
        [$customer, $organizer, $event, , $municipality] = $this->setupDomain();
        $otherCustomer = User::factory()->create(['role' => UserRole::Customer]);
        $otherOrganizer = User::factory()->create(['role' => UserRole::Organizer]);
        $otherProfile = OrganizerProfile::query()->create([
            'ulid' => (string) Str::ulid(),
            'owner_user_id' => $otherOrganizer->id,
            'name' => 'Locale Esterno',
            'type' => 'ristorante',
            'email' => 'esterno@example.test',
            'municipality_id' => $municipality->id,
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);
        $otherEvent = Event::query()->create([
            'ulid' => (string) Str::ulid(),
            'organizer_profile_id' => $otherProfile->id,
            'municipality_id' => $municipality->id,
            'title' => 'Cena esterna',
            'slug' => 'cena-esterna-'.Str::random(6),
            'summary' => 'Evento di altro organizer',
            'description' => 'Recensione non accessibile al primo organizer.',
            'status' => EventStatus::Published,
            'starts_at' => now()->subWeek(),
            'capacity_total' => 10,
            'available_total' => 8,
            'is_free' => false,
            'family_friendly' => false,
            'indoor_outdoor' => 'indoor',
        ]);

        Review::query()->create([
            'ulid' => (string) Str::ulid(),
            'event_id' => $event->id,
            'organizer_profile_id' => $event->organizer_profile_id,
            'user_id' => $customer->id,
            'rating' => 5,
            'experience_quality_rating' => 5,
            'organization_rating' => 5,
            'location_rating' => 5,
            'value_rating' => 5,
            'schedule_respect_rating' => 5,
            'staff_rating' => 5,
            'would_recommend' => true,
            'comment' => 'Recensione propria',
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);
        Review::query()->create([
            'ulid' => (string) Str::ulid(),
            'event_id' => $otherEvent->id,
            'organizer_profile_id' => $otherProfile->id,
            'user_id' => $otherCustomer->id,
            'rating' => 1,
            'experience_quality_rating' => 1,
            'organization_rating' => 1,
            'location_rating' => 1,
            'value_rating' => 1,
            'schedule_respect_rating' => 1,
            'staff_rating' => 1,
            'would_recommend' => false,
            'comment' => 'Recensione altra',
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);

        $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/v1/organizer/reviews')
            ->assertOk()
            ->assertJsonFragment(['comment' => 'Recensione propria'])
            ->assertJsonMissing(['comment' => 'Recensione altra']);
    }

    public function test_organizer_events_include_open_and_closed_summaries(): void
    {
        [$customer, $organizer, $event, , $municipality] = $this->setupDomain();
        $closed = Event::query()->create([
            'ulid' => (string) Str::ulid(),
            'organizer_profile_id' => $event->organizer_profile_id,
            'municipality_id' => $municipality->id,
            'title' => 'Laboratorio concluso',
            'slug' => 'laboratorio-concluso-'.Str::random(6),
            'summary' => 'Evento chiuso',
            'description' => 'Evento concluso con check-in parziale.',
            'status' => EventStatus::Completed,
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->subWeek()->addHours(2),
            'capacity_total' => 10,
            'available_total' => 0,
            'is_free' => true,
            'family_friendly' => true,
            'indoor_outdoor' => 'indoor',
        ]);
        Booking::query()->create([
            'ulid' => (string) Str::ulid(),
            'user_id' => $customer->id,
            'event_id' => $closed->id,
            'status' => 'checked_in',
            'total_people' => 3,
            'adults_count' => 2,
            'children_count' => 1,
            'booking_code' => 'EVT-CLOSED1',
            'qr_token_hash' => 'hash-closed-1',
            'booked_at' => now()->subWeeks(2),
        ]);
        Booking::query()->create([
            'ulid' => (string) Str::ulid(),
            'user_id' => User::factory()->create(['role' => UserRole::Customer])->id,
            'event_id' => $closed->id,
            'status' => 'confirmed',
            'total_people' => 2,
            'adults_count' => 2,
            'children_count' => 0,
            'booking_code' => 'EVT-CLOSED2',
            'qr_token_hash' => 'hash-closed-2',
            'booked_at' => now()->subWeeks(2),
        ]);

        $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/v1/organizer/events?sort=asc')
            ->assertOk()
            ->assertJsonFragment(['visual_status' => 'Aperto'])
            ->assertJsonFragment(['visual_status' => 'Chiuso'])
            ->assertJsonFragment(['checked_in_people' => 3])
            ->assertJsonFragment(['no_show' => 2]);
    }

    public function test_full_event_description_fields_are_saved_and_returned(): void
    {
        [, $organizer, , $categories, $municipality] = $this->setupDomain();

        $response = $this->actingAs($organizer, 'sanctum')
            ->postJson('/api/v1/organizer/events', [
                'title' => 'Cena esperienziale completa',
                'summary' => 'Descrizione breve sicura.',
                'description' => 'Descrizione completa senza HTML non sanificato.',
                'program' => '20:00 accoglienza; 21:00 cena; 23:00 chiusura.',
                'included' => 'Cena e spettacolo.',
                'not_included' => 'Trasporto.',
                'important_information' => 'Arrivare in orario.',
                'requirements' => 'Prenotazione obbligatoria.',
                'recommended_age_range' => '18+',
                'equipment_notes' => 'Abbigliamento comodo.',
                'allergy_notes' => 'Segnalare allergie.',
                'cancellation_policy' => 'Cancellazione entro 48 ore.',
                'organizer_contact' => 'organizer@example.test',
                'show_in_inspiration_feed' => true,
                'municipality_id' => $municipality->id,
                'category_ids' => [$categories['cene']->id],
                'starts_at' => now()->addMonth()->toIso8601String(),
                'capacity_total' => 20,
                'is_free' => false,
                'family_friendly' => false,
                'indoor_outdoor' => 'indoor',
            ])
            ->assertCreated()
            ->assertJsonPath('data.program', '20:00 accoglienza; 21:00 cena; 23:00 chiusura.');

        $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/v1/organizer/events/'.$response->json('data.ulid'))
            ->assertOk()
            ->assertJsonPath('data.included', 'Cena e spettacolo.')
            ->assertJsonPath('data.cancellation_policy', 'Cancellazione entro 48 ore.');
    }

    public function test_calendar_availability_returns_green_yellow_and_red_without_cancelled_events(): void
    {
        [, $organizer, , $categories, $municipality] = $this->setupDomain();
        $otherOrganizer = User::factory()->create(['role' => UserRole::Organizer]);
        $otherProfile = OrganizerProfile::query()->create([
            'ulid' => (string) Str::ulid(),
            'owner_user_id' => $otherOrganizer->id,
            'name' => 'Altro Locale Calendario',
            'type' => 'ristorante',
            'email' => 'calendar@example.test',
            'municipality_id' => $municipality->id,
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);
        $yellowDay = now()->addMonth()->startOfMonth()->addDays(4);
        $redDay = now()->addMonth()->startOfMonth()->addDays(5);

        foreach ([$yellowDay, $redDay, $redDay] as $index => $date) {
            $event = Event::query()->create([
                'ulid' => (string) Str::ulid(),
                'organizer_profile_id' => $otherProfile->id,
                'municipality_id' => $municipality->id,
                'title' => 'Evento calendario '.$index,
                'slug' => 'evento-calendario-'.$index.'-'.Str::random(6),
                'summary' => 'Evento pubblico',
                'description' => 'Evento pubblico concorrente.',
                'status' => $index === 0 ? EventStatus::Published : EventStatus::SoldOut,
                'starts_at' => $date,
                'capacity_total' => 10,
                'available_total' => 2,
                'is_free' => false,
                'family_friendly' => false,
                'indoor_outdoor' => 'indoor',
            ]);
            $event->categories()->sync([$categories['cene']->id]);
        }

        Event::query()->create([
            'ulid' => (string) Str::ulid(),
            'organizer_profile_id' => $otherProfile->id,
            'municipality_id' => $municipality->id,
            'title' => 'Evento annullato',
            'slug' => 'evento-annullato-'.Str::random(6),
            'summary' => 'Non conta',
            'description' => 'Evento annullato da escludere.',
            'status' => EventStatus::Cancelled,
            'starts_at' => $yellowDay,
            'capacity_total' => 10,
            'available_total' => 10,
            'is_free' => true,
            'family_friendly' => false,
            'indoor_outdoor' => 'indoor',
        ]);

        $response = $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/v1/organizer/calendar/availability?month='.$yellowDay->month.'&year='.$yellowDay->year.'&municipality_id='.$municipality->id)
            ->assertOk();

        $days = collect($response->json('data'))->keyBy('date');
        $this->assertSame('low', $days[$yellowDay->toDateString()]['crowding_level']);
        $this->assertSame('yellow', $days[$yellowDay->toDateString()]['color']);
        $this->assertSame(1, $days[$yellowDay->toDateString()]['events_count']);
        $this->assertSame('crowded', $days[$redDay->toDateString()]['crowding_level']);
        $this->assertSame('red', $days[$redDay->toDateString()]['color']);
    }

    public function test_opportunity_fallback_creates_draft_and_inspiration_does_not_copy_private_content(): void
    {
        [, $organizer, $event] = $this->setupDomain();

        $suggestion = $this->actingAs($organizer, 'sanctum')
            ->postJson('/api/v1/organizer/opportunities/generate', ['month' => 2, 'year' => now()->addYear()->year])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'fallback')
            ->json('data');

        $this->actingAs($organizer, 'sanctum')
            ->postJson("/api/v1/organizer/opportunities/{$suggestion['ulid']}/use", ['index' => 0])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        $this->actingAs($organizer, 'sanctum')
            ->postJson("/api/v1/organizer/top-events/{$event->ulid}/inspire")
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonMissing(['title' => $event->title])
            ->assertJsonMissing(['description' => $event->description]);
    }

    public function test_customer_successful_events_show_only_valid_public_aggregate_events_and_rank_by_score(): void
    {
        [$customer, , $event, , $municipality] = $this->setupDomain();
        $series = EventSeries::query()->create([
            'organizer_profile_id' => $event->organizer_profile_id,
            'name' => 'Cena con delitto',
            'category_id' => $event->categories()->first()->id,
            'active' => true,
        ]);
        $winner = $this->successfulEventFixture($event, $municipality->id, $series->id, 'Cena vincente', 93);
        $low = $this->successfulEventFixture($event, $municipality->id, $series->id, 'Cena buona', 65);
        $hidden = $this->successfulEventFixture($event, $municipality->id, $series->id, 'Cena nascosta', 99, show: false);
        $cancelled = $this->successfulEventFixture($event, $municipality->id, $series->id, 'Cena cancellata', 98, status: EventStatus::Cancelled);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/customer/successful-events')
            ->assertOk()
            ->assertJsonPath('data.0.ulid', $winner->ulid)
            ->assertJsonFragment(['title' => $low->title])
            ->assertJsonMissing(['title' => $hidden->title])
            ->assertJsonMissing(['title' => $cancelled->title])
            ->assertJsonMissing(['booking_code' => 'EVT-DEMO01'])
            ->assertJsonMissing(['total_price_cents' => 3200]);
    }

    public function test_customer_ui_renames_explore_to_tutti_gli_eventi(): void
    {
        $app = file_get_contents(base_path('../frontend/src/App.ts'));

        $this->assertStringContainsString('Tutti gli Eventi', $app);
        $this->assertStringNotContainsString('Esplora eventi', $app);
    }

    public function test_customer_dashboard_returns_successful_events_section(): void
    {
        [$customer, , $event, , $municipality] = $this->setupDomain();
        $series = EventSeries::query()->create([
            'organizer_profile_id' => $event->organizer_profile_id,
            'name' => 'Format homepage',
            'category_id' => $event->categories()->first()->id,
            'active' => true,
        ]);
        $past = $this->successfulEventFixture($event, $municipality->id, $series->id, 'Evento amato homepage', 86);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/customer/dashboard')
            ->assertOk()
            ->assertJsonFragment(['title' => $past->title])
            ->assertJsonStructure(['data' => ['successful_events', 'upcoming_events', 'recommended_events']]);
    }

    public function test_customer_successful_event_exposes_next_edition_and_similar_events(): void
    {
        [$customer, , $event, , $municipality] = $this->setupDomain();
        $category = $event->categories()->first();
        $series = EventSeries::query()->create([
            'organizer_profile_id' => $event->organizer_profile_id,
            'name' => 'Laboratorio ricorrente',
            'category_id' => $category->id,
            'active' => true,
        ]);
        $past = $this->successfulEventFixture($event, $municipality->id, $series->id, 'Laboratorio passato', 88);
        $futureEdition = Event::query()->create([
            'ulid' => (string) Str::ulid(),
            'organizer_profile_id' => $event->organizer_profile_id,
            'event_series_id' => $series->id,
            'municipality_id' => $municipality->id,
            'title' => 'Laboratorio nuova edizione',
            'slug' => 'laboratorio-nuova-'.Str::random(6),
            'summary' => 'Nuova edizione prenotabile',
            'description' => 'Nuova edizione futura',
            'status' => EventStatus::Published,
            'starts_at' => now()->addMonth(),
            'capacity_total' => 25,
            'available_total' => 14,
            'is_free' => false,
            'family_friendly' => true,
            'indoor_outdoor' => 'mixed',
            'show_in_inspiration_feed' => true,
        ]);
        $futureEdition->categories()->sync([$category->id]);

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/v1/customer/successful-events/{$past->ulid}")
            ->assertOk()
            ->assertJsonPath('data.has_next_edition', true)
            ->assertJsonPath('data.next_edition.title', 'Laboratorio nuova edizione')
            ->assertJsonPath('data.next_editions.0.bookable', true)
            ->assertJsonFragment(['title' => 'Laboratorio nuova edizione']);
    }

    public function test_customer_can_enable_and_disable_successful_event_notify_similar_preference(): void
    {
        [$customer, , $event, , $municipality] = $this->setupDomain();
        $series = EventSeries::query()->create([
            'organizer_profile_id' => $event->organizer_profile_id,
            'name' => 'Format notifica',
            'category_id' => $event->categories()->first()->id,
            'active' => true,
        ]);
        $past = $this->successfulEventFixture($event, $municipality->id, $series->id, 'Evento da seguire', 82);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/customer/successful-events/{$past->ulid}/notify-similar")
            ->assertCreated()
            ->assertJsonPath('data.notify_similar', true);

        $this->assertDatabaseHas('customer_event_format_interests', [
            'user_id' => $customer->id,
            'event_id' => $past->id,
            'event_series_id' => $series->id,
            'notify_similar' => true,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->deleteJson("/api/v1/customer/successful-events/{$past->ulid}/notify-similar")
            ->assertOk();

        $this->assertDatabaseMissing('customer_event_format_interests', [
            'user_id' => $customer->id,
            'event_id' => $past->id,
        ]);
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

    public function test_customer_can_review_after_confirmed_booking_and_event_conclusion(): void
    {
        [$customer, , $event] = $this->setupDomain();
        $event->forceFill(['starts_at' => now()->subDay(), 'ends_at' => now()->subHours(2)])->save();

        Booking::query()->create([
            'ulid' => (string) Str::ulid(),
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'status' => 'confirmed',
            'total_people' => 1,
            'adults_count' => 1,
            'children_count' => 0,
            'booking_code' => 'EVT-REV01',
            'qr_token_hash' => 'hash-review',
            'booked_at' => now()->subDays(2),
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/events/{$event->ulid}/reviews", $this->reviewPayload())
            ->assertCreated()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.would_recommend', true);

        $this->assertDatabaseHas('reviews', [
            'event_id' => $event->id,
            'user_id' => $customer->id,
            'rating' => 5,
            'status' => 'approved',
        ]);
    }

    public function test_customer_cannot_review_without_real_participation_or_twice(): void
    {
        [$customer, , $event] = $this->setupDomain();

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/events/{$event->ulid}/reviews", $this->reviewPayload())
            ->assertForbidden();

        $event->forceFill(['starts_at' => now()->subDay(), 'ends_at' => now()->subHours(2)])->save();
        Booking::query()->create([
            'ulid' => (string) Str::ulid(),
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'status' => 'checked_in',
            'total_people' => 1,
            'adults_count' => 1,
            'children_count' => 0,
            'booking_code' => 'EVT-REV02',
            'qr_token_hash' => 'hash-review-2',
            'booked_at' => now()->subDays(2),
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/events/{$event->ulid}/reviews", $this->reviewPayload())
            ->assertCreated();

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/events/{$event->ulid}/reviews", $this->reviewPayload())
            ->assertUnprocessable();
    }

    public function test_organizer_cannot_review_own_event_and_review_can_be_reported(): void
    {
        [$customer, $organizer, $event] = $this->setupDomain();
        $event->forceFill(['starts_at' => now()->subDay(), 'ends_at' => now()->subHours(2)])->save();

        Booking::query()->create([
            'ulid' => (string) Str::ulid(),
            'user_id' => $organizer->id,
            'event_id' => $event->id,
            'status' => 'checked_in',
            'total_people' => 1,
            'adults_count' => 1,
            'children_count' => 0,
            'booking_code' => 'EVT-ORG01',
            'qr_token_hash' => 'hash-org-review',
            'booked_at' => now()->subDays(2),
        ]);

        $this->actingAs($organizer, 'sanctum')
            ->postJson("/api/v1/events/{$event->ulid}/reviews", $this->reviewPayload())
            ->assertForbidden();

        $review = Review::query()->create([
            'ulid' => (string) Str::ulid(),
            'event_id' => $event->id,
            'organizer_profile_id' => $event->organizer_profile_id,
            'user_id' => $customer->id,
            'rating' => 2,
            'experience_quality_rating' => 2,
            'organization_rating' => 2,
            'location_rating' => 3,
            'value_rating' => 2,
            'schedule_respect_rating' => 3,
            'staff_rating' => 2,
            'would_recommend' => false,
            'comment' => 'Troppo affollato.',
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/reviews/{$review->ulid}/report", ['reason' => 'contenuto improprio'])
            ->assertCreated()
            ->assertJsonPath('data.reason', 'contenuto improprio');
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

    private function successfulEventFixture(Event $baseEvent, int $municipalityId, int $seriesId, string $title, int $score, bool $show = true, EventStatus $status = EventStatus::Completed): Event
    {
        $event = Event::query()->create([
            'ulid' => (string) Str::ulid(),
            'organizer_profile_id' => $baseEvent->organizer_profile_id,
            'event_series_id' => $seriesId,
            'municipality_id' => $municipalityId,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(6),
            'summary' => 'Evento concluso con metriche aggregate.',
            'description' => 'Descrizione pubblica evento concluso.',
            'status' => $status,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonths(2)->addHours(2),
            'capacity_total' => 50,
            'available_total' => 0,
            'is_free' => false,
            'family_friendly' => true,
            'indoor_outdoor' => 'mixed',
            'show_in_inspiration_feed' => $show,
        ]);
        $event->categories()->sync($baseEvent->categories()->pluck('categories.id'));

        EventSuccessScore::query()->create([
            'event_id' => $event->id,
            'fill_rate' => $score,
            'attendance_rate' => max(0, $score - 3),
            'no_show_rate' => 5,
            'average_rating' => 4.6,
            'review_count' => 8,
            'recommendation_rate' => 90,
            'favorite_count' => 12,
            'success_score' => $score,
            'calculated_at' => now(),
        ]);

        return $event;
    }

    private function reviewPayload(): array
    {
        return [
            'rating' => 5,
            'experience_quality_rating' => 5,
            'organization_rating' => 4,
            'location_rating' => 5,
            'value_rating' => 4,
            'schedule_respect_rating' => 5,
            'staff_rating' => 5,
            'suitable_for_children' => true,
            'would_recommend' => true,
            'comment' => 'Esperienza curata e facile da consigliare.',
            'images' => [
                ['path' => 'reviews/demo.jpg', 'disk' => 's3', 'mime_type' => 'image/jpeg', 'size_bytes' => 1024],
            ],
        ];
    }
}
