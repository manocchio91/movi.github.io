<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\ActivityService;
use App\Models\Category;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\OrganizerProfile;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnifiedActivityDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_configures_existing_taxonomy_with_tenant_isolation(): void
    {
        [$owner, $profile, $municipality] = $this->domain('owner@example.test');
        [$otherOwner] = $this->domain('other@example.test', $municipality);
        $category = Category::query()->create(['name' => 'Gastronomia', 'slug' => 'gastronomia', 'is_active' => true]);
        $activity = $this->activity($profile, $municipality, 'Ristorante reale');
        $payload = ['name' => $activity->name, 'type' => $activity->type->value, 'description' => null, 'municipality_id' => $municipality->id, 'booking_enabled' => true, 'status' => 'published', 'category_ids' => [$category->id]];

        $this->actingAs($otherOwner, 'sanctum')->putJson("/api/v1/organizer/activities/{$activity->ulid}", $payload)->assertForbidden();
        $this->actingAs($owner, 'sanctum')->putJson("/api/v1/organizer/activities/{$activity->ulid}", $payload)
            ->assertOk()->assertJsonPath('data.categories.0.slug', 'gastronomia');
        $this->assertDatabaseHas('activity_categories', ['activity_id' => $activity->id, 'category_id' => $category->id]);
    }

    public function test_interest_and_family_filters_return_only_compatible_published_events_and_activities(): void
    {
        [, $profile, $municipality] = $this->domain('discovery@example.test');
        $customer = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active']);
        $gastronomy = Category::query()->create(['name' => 'Gastronomia', 'slug' => 'gastronomia', 'is_active' => true]);
        $family = Category::query()->create(['name' => 'Famiglia', 'slug' => 'famiglia', 'is_active' => true]);
        $sport = Category::query()->create(['name' => 'Sport', 'slug' => 'sport', 'is_active' => true]);
        $restaurant = $this->activity($profile, $municipality, 'Cena autentica', $gastronomy);
        $familyActivity = $this->activity($profile, $municipality, 'Laboratorio famiglie', $family, '16:00');
        $unrelated = $this->activity($profile, $municipality, 'Campo sportivo', $sport);
        $hidden = $this->activity($profile, $municipality, 'Locale nascosto', $gastronomy, '20:30', ActivityStatus::Draft);
        $event = $this->event($profile, $municipality, 'Festival del gusto', $gastronomy, '21:00');
        $familyEvent = $this->event($profile, $municipality, 'Festa bambini', $family, '18:00', true);

        $rows = $this->actingAs($customer, 'sanctum')->getJson("/api/v1/discovery?when=tomorrow&category_id={$gastronomy->id}&available=1&latitude=41.56&longitude=14.66&radius_km=10")
            ->assertOk()->json('data');
        $ids = collect($rows)->pluck('entity_id');
        $this->assertTrue($ids->contains($restaurant->ulid));
        $this->assertTrue($ids->contains($event->ulid));
        $this->assertFalse($ids->contains($unrelated->ulid));
        $this->assertFalse($ids->contains($hidden->ulid));
        $this->assertSame($ids->all(), collect($rows)->sort(function (array $left, array $right): int {
            $score = $right['recommendation_score'] <=> $left['recommendation_score'];

            return $score !== 0 ? $score : [$left['entity_type'], $left['entity_id']] <=> [$right['entity_type'], $right['entity_id']];
        })->pluck('entity_id')->all());

        $familyIds = collect($this->getJson('/api/v1/discovery?when=tomorrow&family=1&available=1')->assertOk()->json('data'))->pluck('entity_id');
        $this->assertTrue($familyIds->contains($familyActivity->ulid));
        $this->assertTrue($familyIds->contains($familyEvent->ulid));
    }

    public function test_plan_combines_real_activity_and_event_without_inventing_unknown_price(): void
    {
        [, $profile, $municipality] = $this->domain('plan-unified@example.test');
        $customer = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active']);
        $music = Category::query()->create(['name' => 'Musica', 'slug' => 'musica', 'is_active' => true]);
        $activity = $this->activity($profile, $municipality, 'Aperitivo musicale', $music, '18:00');
        $event = $this->event($profile, $municipality, 'Concerto vero', $music, '21:00');
        $event->ticketTypes()->create(['ulid' => (string) Str::ulid(), 'name' => 'Ingresso', 'audience' => 'generic', 'price_cents' => 1200, 'is_active' => true, 'booked_quantity' => 0]);

        $plans = $this->actingAs($customer, 'sanctum')->postJson('/api/v1/plans/generate', ['when' => 'tomorrow', 'category_id' => $music->id, 'max_steps' => 2, 'budget_cents' => 2000])
            ->assertOk()->json('data');
        $combined = collect($plans)->first(fn (array $plan) => collect($plan['steps'])->pluck('entity_type')->sort()->values()->all() === ['activity', 'event']);
        $this->assertNotNull($combined);
        $this->assertSame([$activity->ulid, $event->ulid], collect($combined['steps'])->pluck('entity_id')->all());
        $this->assertNull($combined['known_total_price_cents']);
        $this->assertSame('partial', $combined['budget_verification']);
        $this->assertNull($combined['steps'][0]['price_cents']);
        $this->assertSame(1200, $combined['steps'][1]['price_cents']);
    }

    private function domain(string $email, ?Municipality $municipality = null): array
    {
        $owner = User::factory()->create(['role' => UserRole::Organizer, 'status' => 'active', 'email' => $email]);
        if ($municipality === null) {
            $region = Region::query()->create(['name' => 'Molise', 'code' => Str::upper(Str::random(3))]);
            $province = Province::query()->create(['region_id' => $region->id, 'name' => 'Campobasso', 'code' => Str::upper(Str::random(2))]);
            $municipality = Municipality::query()->create(['province_id' => $province->id, 'name' => 'Campobasso', 'istat_code' => Str::random(6), 'latitude' => 41.5603, 'longitude' => 14.6627]);
        }
        $profile = OrganizerProfile::query()->create(['ulid' => (string) Str::ulid(), 'owner_user_id' => $owner->id, 'name' => 'Organizer '.$owner->id, 'type' => 'association', 'email' => $email, 'municipality_id' => $municipality->id, 'verification_status' => 'verified']);

        return [$owner, $profile, $municipality];
    }

    private function activity(OrganizerProfile $profile, Municipality $municipality, string $name, ?Category $category = null, string $time = '20:00', ActivityStatus $status = ActivityStatus::Published): Activity
    {
        $activity = Activity::query()->create(['ulid' => (string) Str::ulid(), 'organizer_profile_id' => $profile->id, 'municipality_id' => $municipality->id, 'name' => $name, 'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)), 'type' => ActivityType::Restaurant, 'status' => $status, 'booking_enabled' => true]);
        if ($category !== null) {
            $activity->categories()->attach($category);
        }
        $service = ActivityService::query()->create(['ulid' => (string) Str::ulid(), 'activity_id' => $activity->id, 'name' => $name, 'type' => 'experience', 'active' => true, 'booking_enabled' => true, 'min_party_size' => 1, 'max_party_size' => 8, 'minimum_advance_minutes' => 0, 'indicative_duration_minutes' => 90, 'capacity_mode' => 'covers']);
        $service->availabilityRules()->create(['weekday' => now()->addDay()->dayOfWeekIso, 'start_time' => $time, 'capacity' => 10, 'active' => true]);

        return $activity;
    }

    private function event(OrganizerProfile $profile, Municipality $municipality, string $title, Category $category, string $time, bool $family = false): Event
    {
        $event = Event::query()->create(['ulid' => (string) Str::ulid(), 'organizer_profile_id' => $profile->id, 'municipality_id' => $municipality->id, 'title' => $title, 'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)), 'summary' => 'Reale', 'description' => 'Reale', 'status' => EventStatus::Published, 'starts_at' => now()->addDay()->setTimeFromTimeString($time), 'ends_at' => now()->addDay()->setTimeFromTimeString($time)->addHour(), 'capacity_total' => 30, 'available_total' => 20, 'is_free' => false, 'family_friendly' => $family, 'indoor_outdoor' => 'mixed']);
        $event->categories()->attach($category);

        return $event;
    }
}
