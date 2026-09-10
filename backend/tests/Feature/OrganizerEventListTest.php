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
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrganizerEventListTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_role_and_approval_are_required(): void
    {
        [$organizer, $profile] = $this->organizerFixture();
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        $this->getJson('/api/v1/organizer/events')->assertUnauthorized();
        $this->actingAs($customer, 'sanctum')->getJson('/api/v1/organizer/events')->assertForbidden();

        $profile->forceFill(['verification_status' => 'pending', 'verified_at' => null])->save();
        $this->actingAs($organizer, 'sanctum')->getJson('/api/v1/organizer/events')->assertForbidden();
    }

    public function test_filters_match_dashboard_classification_and_keep_cancelled_separate(): void
    {
        [$organizer, $profile, $municipality] = $this->organizerFixture();

        $futurePublished = $this->eventFixture($profile, $municipality, EventStatus::Published, 'Futuro pubblicato', now()->addDay());
        $futureSoldOut = $this->eventFixture($profile, $municipality, EventStatus::SoldOut, 'Futuro esaurito', now()->addDays(2));
        $this->eventFixture($profile, $municipality, EventStatus::Published, 'Pubblicato passato', now()->subDay());
        $completed = $this->eventFixture($profile, $municipality, EventStatus::Completed, 'Concluso', now()->subDays(2));
        $unpublished = collect([
            EventStatus::Draft,
            EventStatus::PendingReview,
            EventStatus::Rejected,
            EventStatus::Suspended,
            EventStatus::Archived,
        ])->map(fn (EventStatus $status) => $this->eventFixture($profile, $municipality, $status, 'Non pubblicato '.$status->value, now()->addWeek()));
        $cancelled = $this->eventFixture($profile, $municipality, EventStatus::Cancelled, 'Annullato', now()->addWeek());

        $futureResponse = $this->actingAs($organizer, 'sanctum')->getJson('/api/v1/organizer/events?filter=future');
        $futureResponse->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
        $this->assertEqualsCanonicalizing(
            [$futurePublished->ulid, $futureSoldOut->ulid],
            collect($futureResponse->json('data'))->pluck('event.ulid')->all(),
        );

        $completedResponse = $this->actingAs($organizer, 'sanctum')->getJson('/api/v1/organizer/events?filter=completed');
        $completedResponse->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.event.ulid', $completed->ulid);

        $unpublishedResponse = $this->actingAs($organizer, 'sanctum')->getJson('/api/v1/organizer/events?filter=unpublished&per_page=10');
        $unpublishedResponse->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('meta.total', 5);
        $this->assertEqualsCanonicalizing(
            $unpublished->pluck('ulid')->all(),
            collect($unpublishedResponse->json('data'))->pluck('event.ulid')->all(),
        );

        $cancelledResponse = $this->actingAs($organizer, 'sanctum')->getJson('/api/v1/organizer/events?filter=cancelled');
        $cancelledResponse->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.event.ulid', $cancelled->ulid);
    }

    public function test_pagination_metadata_and_organizer_isolation_are_real(): void
    {
        [$organizer, $profile, $municipality] = $this->organizerFixture();
        [$otherOrganizer, $otherProfile, $otherMunicipality] = $this->organizerFixture();

        foreach (range(1, 13) as $index) {
            $this->eventFixture($profile, $municipality, EventStatus::Published, "Evento {$index}", now()->addDays($index));
        }
        $otherEvent = $this->eventFixture($otherProfile, $otherMunicipality, EventStatus::Published, 'Evento esterno', now()->addDay());

        $response = $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/v1/organizer/events?filter=future&per_page=5&page=2&sort=asc');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 13)
            ->assertJsonMissing(['ulid' => $otherEvent->ulid]);

        $this->actingAs($otherOrganizer, 'sanctum')
            ->getJson('/api/v1/organizer/events?per_page=50')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event.ulid', $otherEvent->ulid)
            ->assertJsonMissing(['ulid' => $profile->events()->firstOrFail()->ulid]);
    }

    public function test_event_summary_tracks_booking_create_update_and_cancellation(): void
    {
        [$organizer, $profile, $municipality] = $this->organizerFixture();
        $event = $this->eventFixture($profile, $municipality, EventStatus::Published, 'Evento riepilogo', now()->addWeek());
        $event->forceFill(['capacity_total' => 30, 'available_total' => 26])->save();
        $customer = User::factory()->create(['role' => UserRole::Customer]);
        $adult = TicketType::query()->create(['event_id' => $event->id, 'name' => 'Adulto', 'audience' => 'adult', 'max_quantity' => 30, 'booked_quantity' => 2, 'price_cents' => 1000, 'is_active' => true]);
        $child = TicketType::query()->create(['event_id' => $event->id, 'name' => 'Bambino', 'audience' => 'child', 'max_quantity' => 30, 'booked_quantity' => 2, 'price_cents' => 500, 'is_active' => true]);
        $first = $this->booking($event, $customer, 2);
        $second = $this->booking($event, $customer, 2);
        $first->items()->create(['ticket_type_id' => $adult->id, 'quantity' => 2, 'unit_price_cents' => 1000, 'line_total_cents' => 2000]);
        $second->items()->create(['ticket_type_id' => $child->id, 'quantity' => 2, 'unit_price_cents' => 500, 'line_total_cents' => 1000]);

        $this->assertSummary($organizer, 30, 4, 26);

        $first->forceFill(['total_people' => 4, 'adults_count' => 4])->save();
        $event->forceFill(['available_total' => 24])->save();
        $this->assertSummary($organizer, 30, 6, 24);

        Booking::query()->whereIn('id', [$first->id, $second->id])->update(['status' => 'cancelled_by_customer']);
        $event->forceFill(['available_total' => 30])->save();
        $this->assertSummary($organizer, 30, 0, 30);
    }

    public function test_event_summary_uses_real_ticket_availability_below_global_capacity(): void
    {
        [$organizer, $profile, $municipality] = $this->organizerFixture();
        $event = $this->eventFixture($profile, $municipality, EventStatus::Published, 'Disponibilità ticket', now()->addWeek());
        $event->forceFill(['capacity_total' => 50, 'available_total' => 50])->save();
        TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Ingresso',
            'audience' => 'generic',
            'max_quantity' => 40,
            'booked_quantity' => 0,
            'price_cents' => 1000,
            'is_active' => true,
        ]);

        $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/v1/organizer/events?filter=future&per_page=50')
            ->assertOk()
            ->assertJsonPath('data.0.summary.capacity_total', 50)
            ->assertJsonPath('data.0.summary.capacity_available_total', 50)
            ->assertJsonPath('data.0.summary.available_total', 40);
    }

    public function test_invalid_filter_and_pagination_values_are_rejected(): void
    {
        [$organizer] = $this->organizerFixture();

        $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/v1/organizer/events?filter=open&per_page=100')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filter', 'per_page']);
    }

    private function booking(Event $event, User $customer, int $people): Booking
    {
        return Booking::query()->create([
            'ulid' => (string) Str::ulid(),
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'status' => 'confirmed',
            'total_people' => $people,
            'adults_count' => $people,
            'children_count' => 0,
            'booking_code' => Str::upper(Str::random(12)),
            'qr_token_hash' => hash('sha256', Str::random(32)),
            'booked_at' => now(),
        ]);
    }

    private function assertSummary(User $organizer, int $capacity, int $booked, int $available): void
    {
        $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/v1/organizer/events?per_page=50')
            ->assertOk()
            ->assertJsonPath('data.0.summary.capacity_total', $capacity)
            ->assertJsonPath('data.0.summary.booked_people', $booked)
            ->assertJsonPath('data.0.summary.available_total', $available);
    }

    /** @return array{User, OrganizerProfile, Municipality} */
    private function organizerFixture(): array
    {
        $organizer = User::factory()->create([
            'role' => UserRole::Organizer,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $region = Region::query()->create(['name' => 'Molise '.Str::random(5), 'code' => Str::upper(Str::random(3))]);
        $province = Province::query()->create(['region_id' => $region->id, 'name' => 'Campobasso', 'code' => Str::upper(Str::random(2))]);
        $municipality = Municipality::query()->create(['province_id' => $province->id, 'name' => 'Campobasso', 'istat_code' => Str::random(6)]);
        $profile = OrganizerProfile::query()->create([
            'ulid' => (string) Str::ulid(),
            'owner_user_id' => $organizer->id,
            'name' => 'Organizer '.Str::random(5),
            'type' => 'associazione',
            'email' => Str::random(8).'@example.test',
            'municipality_id' => $municipality->id,
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        return [$organizer, $profile, $municipality];
    }

    private function eventFixture(
        OrganizerProfile $profile,
        Municipality $municipality,
        EventStatus $status,
        string $title,
        CarbonInterface $startsAt,
    ): Event {
        return Event::query()->create([
            'ulid' => (string) Str::ulid(),
            'organizer_profile_id' => $profile->id,
            'municipality_id' => $municipality->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(6),
            'summary' => 'Evento per test lista Organizer.',
            'description' => 'Descrizione evento per test lista Organizer.',
            'status' => $status,
            'starts_at' => $startsAt,
            'capacity_total' => 20,
            'available_total' => 12,
            'is_free' => true,
            'family_friendly' => false,
            'indoor_outdoor' => 'outdoor',
        ]);
    }
}
