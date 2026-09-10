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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrganizerDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-01 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_verified_organizer_receives_the_dashboard_contract(): void
    {
        [$organizer] = $this->createOrganizer('Dashboard');

        $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/v1/organizer/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'bi' => [
                        'total_events',
                        'future_events',
                        'completed_events',
                        'unpublished_events',
                        'bookings_received',
                        'participants_total',
                        'impressions',
                        'detail_views',
                        'shares',
                        'cancellations',
                        'waitlist_joined',
                        'waitlist_converted',
                        'check_in_people',
                        'no_show_people',
                        'booking_conversion_rate',
                        'attendance_rate',
                        'cancellation_rate',
                        'no_show_rate',
                        'review_rate',
                        'follow_conversion_rate',
                    ],
                    'unpublished_experiences',
                ],
            ]);
    }

    public function test_event_kpis_use_the_defined_status_classifications_and_isolate_organizers(): void
    {
        [$organizer, $profile, $municipality] = $this->createOrganizer('Primary');
        [, $otherProfile, $otherMunicipality] = $this->createOrganizer('Other');

        $this->createEvent($profile, $municipality, EventStatus::Published, now()->addDay());
        $this->createEvent($profile, $municipality, EventStatus::SoldOut, now()->addDays(2));
        $this->createEvent($profile, $municipality, EventStatus::Published, now()->subDay());
        $this->createEvent($profile, $municipality, EventStatus::Draft, now()->addDays(3));
        $this->createEvent($profile, $municipality, EventStatus::PendingReview, now()->addDays(4));
        $this->createEvent($profile, $municipality, EventStatus::Rejected, now()->addDays(5));
        $this->createEvent($profile, $municipality, EventStatus::Suspended, now()->addDays(6));
        $this->createEvent($profile, $municipality, EventStatus::Archived, now()->addDays(7));
        $this->createEvent($profile, $municipality, EventStatus::Cancelled, now()->addDays(8));
        $this->createEvent($profile, $municipality, EventStatus::Completed, now()->addDays(9));
        $this->createEvent($otherProfile, $otherMunicipality, EventStatus::Published, now()->addDay());

        $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/v1/organizer/dashboard')
            ->assertOk()
            ->assertJsonPath('data.bi.total_events', 10)
            ->assertJsonPath('data.bi.future_events', 2)
            ->assertJsonPath('data.bi.completed_events', 1)
            ->assertJsonPath('data.bi.unpublished_events', 5)
            ->assertJsonPath('data.bi.cancelled_events', 1)
            ->assertJsonPath('data.active_experiences', 2)
            ->assertJsonPath('data.future_experiences', 2)
            ->assertJsonPath('data.completed_experiences', 1)
            ->assertJsonPath('data.unpublished_experiences', 5);
    }

    public function test_booking_kpis_include_every_valid_status_and_exclude_cancellations(): void
    {
        [$organizer, $profile, $municipality] = $this->createOrganizer('Bookings');
        $event = $this->createEvent($profile, $municipality, EventStatus::Published, now()->addDay());
        $returningCustomer = User::factory()->create(['role' => UserRole::Customer]);
        $otherCustomer = User::factory()->create(['role' => UserRole::Customer]);

        $this->createBooking($event, $returningCustomer, 'confirmed', 2);
        $this->createBooking($event, $returningCustomer, 'checked_in', 3);
        $this->createBooking($event, $otherCustomer, 'completed', 4);
        $this->createBooking($event, $otherCustomer, 'cancelled_by_customer', 5);
        $this->createBooking($event, $otherCustomer, 'cancelled_by_organizer', 6);

        $response = $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/v1/organizer/dashboard')
            ->assertOk()
            ->assertJsonPath('data.bi.bookings_received', 3)
            ->assertJsonPath('data.bi.participants_total', 9)
            ->assertJsonPath('data.bi.unique_participants', 2)
            ->assertJsonPath('data.bi.returning_participants', 1)
            ->assertJsonPath('data.bi.cancellations', 2)
            ->assertJsonPath('data.bi.cancellation_rate', 40)
            ->assertJsonPath('data.bookings_total', 3)
            ->assertJsonPath('data.participants_total', 9);

        $this->assertSame(
            $response->json('data.bi.bookings_received'),
            $response->json('data.bookings_total'),
        );
        $this->assertSame(
            $response->json('data.bi.participants_total'),
            $response->json('data.participants_total'),
        );
    }

    public function test_operational_places_and_top_level_values_match_business_intelligence(): void
    {
        [$organizer, $profile, $municipality] = $this->createOrganizer('Places');
        $this->createEvent($profile, $municipality, EventStatus::Published, now()->addDay(), 10, 7);
        $this->createEvent($profile, $municipality, EventStatus::SoldOut, now()->addDays(2), 8, 0);
        $this->createEvent($profile, $municipality, EventStatus::Draft, now()->addDays(3), 100, 90);
        $this->createEvent($profile, $municipality, EventStatus::Published, now()->subDay(), 50, 40);

        $response = $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/v1/organizer/dashboard')
            ->assertOk()
            ->assertJsonPath('data.bi.available_places', 7)
            ->assertJsonPath('data.bi.occupied_places', 11);

        foreach ([
            'future_experiences' => 'future_events',
            'completed_experiences' => 'completed_events',
            'unpublished_experiences' => 'unpublished_events',
            'bookings_total' => 'bookings_received',
            'available_places' => 'available_places',
            'participants_total' => 'participants_total',
            'views_total' => 'views_total',
            'followers' => 'followers_total',
        ] as $topLevel => $bi) {
            $this->assertSame($response->json("data.bi.{$bi}"), $response->json("data.{$topLevel}"));
        }

        $this->assertSame($response->json('data.bi.future_events'), $response->json('data.active_experiences'));
    }

    public function test_statistics_endpoint_uses_the_same_valid_booking_semantics(): void
    {
        [$organizer, $profile, $municipality] = $this->createOrganizer('Statistics');
        $event = $this->createEvent($profile, $municipality, EventStatus::Published, now()->addDay());
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        $this->createBooking($event, $customer, 'confirmed', 2);
        $this->createBooking($event, $customer, 'checked_in', 3);
        $this->createBooking($event, $customer, 'completed', 4);
        $this->createBooking($event, $customer, 'cancelled_by_customer', 5);

        $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/v1/organizer/statistics')
            ->assertOk()
            ->assertJsonPath('data.bookings_received', 3)
            ->assertJsonPath('data.participants_total', 9);
    }

    /** @return array{User, OrganizerProfile, Municipality} */
    private function createOrganizer(string $suffix): array
    {
        $organizer = User::factory()->create([
            'role' => UserRole::Organizer,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $region = Region::query()->create([
            'name' => "Region {$suffix}",
            'code' => Str::upper(Str::random(3)),
        ]);
        $province = Province::query()->create([
            'region_id' => $region->id,
            'name' => "Province {$suffix}",
            'code' => Str::upper(Str::random(2)),
        ]);
        $municipality = Municipality::query()->create([
            'province_id' => $province->id,
            'name' => "Municipality {$suffix}",
            'istat_code' => Str::random(6),
        ]);
        $profile = OrganizerProfile::query()->create([
            'ulid' => (string) Str::ulid(),
            'owner_user_id' => $organizer->id,
            'name' => "Organizer {$suffix}",
            'type' => 'association',
            'email' => Str::lower($suffix).'@example.test',
            'municipality_id' => $municipality->id,
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        return [$organizer, $profile, $municipality];
    }

    private function createEvent(
        OrganizerProfile $profile,
        Municipality $municipality,
        EventStatus $status,
        Carbon $startsAt,
        int $capacity = 20,
        int $available = 10,
    ): Event {
        $token = Str::lower(Str::random(10));

        return Event::query()->create([
            'ulid' => (string) Str::ulid(),
            'organizer_profile_id' => $profile->id,
            'municipality_id' => $municipality->id,
            'title' => "Event {$token}",
            'slug' => "event-{$token}",
            'summary' => 'Organizer dashboard event fixture.',
            'description' => 'Event created to verify the organizer dashboard contract.',
            'status' => $status,
            'starts_at' => $startsAt,
            'capacity_total' => $capacity,
            'available_total' => $available,
            'is_free' => true,
            'family_friendly' => false,
            'indoor_outdoor' => 'indoor',
        ]);
    }

    private function createBooking(Event $event, User $customer, string $status, int $people): Booking
    {
        $token = Str::upper(Str::random(10));

        return Booking::query()->create([
            'ulid' => (string) Str::ulid(),
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'status' => $status,
            'total_people' => $people,
            'adults_count' => $people,
            'children_count' => 0,
            'booking_code' => "DASH-{$token}",
            'qr_token_hash' => "dashboard-{$token}",
            'booked_at' => now(),
            'event_terms_accepted' => true,
        ]);
    }
}
