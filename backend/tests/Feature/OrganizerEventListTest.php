<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\OrganizerProfile;
use App\Models\Province;
use App\Models\Region;
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
        [, $otherProfile, $otherMunicipality] = $this->organizerFixture();

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
    }

    public function test_invalid_filter_and_pagination_values_are_rejected(): void
    {
        [$organizer] = $this->organizerFixture();

        $this->actingAs($organizer, 'sanctum')
            ->getJson('/api/v1/organizer/events?filter=open&per_page=100')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filter', 'per_page']);
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
