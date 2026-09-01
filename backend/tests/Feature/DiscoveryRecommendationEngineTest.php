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

class DiscoveryRecommendationEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_temporal_type_category_availability_distance_ranking_and_analytics(): void
    {
        [$customer, $organizer, $municipality, $category] = $this->domain();
        $event = $this->event($organizer, $municipality, $category, now()->addHours(3));
        $soldOut = $this->event($organizer, $municipality, $category, now()->addHours(4), 0);

        $this->actingAs($customer, 'sanctum')->withHeader('X-Analytics-Session', 'discovery-test')->getJson("/api/v1/discovery?when=today&type=event&category_id={$category->id}&available=1&latitude=41.56&longitude=14.66&radius_km=15")
            ->assertOk()->assertJsonPath('data.0.entity_id', $event->ulid)->assertJsonMissing(['entity_id' => $soldOut->ulid])->assertJsonPath('data.0.availability_status', 'available');
        $this->assertDatabaseHas('analytics_events', ['type' => 'discovery_filter_applied', 'user_id' => $customer->id]);
    }

    public function test_activity_slots_interest_profile_reasons_and_pagination(): void
    {
        [$customer, $organizer, $municipality, $category] = $this->domain();
        $event = $this->event($organizer, $municipality, $category, now()->addDay());
        $activity = Activity::query()->create(['ulid' => (string) Str::ulid(), 'organizer_profile_id' => $organizer->id, 'municipality_id' => $municipality->id, 'name' => 'Cena locale', 'slug' => 'cena-locale', 'type' => ActivityType::Restaurant, 'status' => ActivityStatus::Published, 'booking_enabled' => true]);
        $service = ActivityService::query()->create(['ulid' => (string) Str::ulid(), 'activity_id' => $activity->id, 'name' => 'Cena', 'type' => 'dinner', 'active' => true, 'booking_enabled' => true, 'min_party_size' => 1, 'max_party_size' => 8, 'minimum_advance_minutes' => 0, 'indicative_duration_minutes' => 90, 'capacity_mode' => 'covers']);
        $service->availabilityRules()->create(['weekday' => now()->addDay()->dayOfWeekIso, 'start_time' => '20:00', 'capacity' => 10, 'active' => true]);
        $customer->preferredCategories()->sync([$category->id]);
        $customer->followedOrganizers()->attach($organizer->id);
        $customer->followedActivities()->attach($activity->id);

        $this->actingAs($customer, 'sanctum')->withHeader('X-Analytics-Session', 'recommendation-test')->getJson('/api/v1/recommendations?when=tomorrow&per_page=1')
            ->assertOk()->assertJsonPath('per_page', 1)->assertJsonPath('data.0.entity_id', $activity->ulid)
            ->assertJsonFragment(['recommendation_reasons' => ['Organizer che segui', 'Attività che segui', 'Disponibile nel periodo scelto']]);
        $this->assertDatabaseHas('analytics_events', ['type' => 'recommendation_impression']);
        $this->postJson('/api/v1/recommendations/opened', ['entity_type' => 'activity', 'entity_id' => $activity->ulid])->assertOk();
        $this->assertDatabaseHas('analytics_events', ['type' => 'recommendation_opened', 'activity_id' => $activity->id]);
        $this->assertNotSame($event->ulid, $activity->ulid);
    }

    private function domain(): array
    {
        $customer = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active']);
        $owner = User::factory()->create(['role' => UserRole::Organizer, 'status' => 'active']);
        $region = Region::query()->create(['name' => 'Molise', 'code' => 'MOL']);
        $province = Province::query()->create(['region_id' => $region->id, 'name' => 'Campobasso', 'code' => 'CB']);
        $municipality = Municipality::query()->create(['province_id' => $province->id, 'name' => 'Campobasso', 'istat_code' => '070006', 'latitude' => 41.5603, 'longitude' => 14.6627]);
        $organizer = OrganizerProfile::query()->create(['ulid' => (string) Str::ulid(), 'owner_user_id' => $owner->id, 'name' => 'Scoperte', 'type' => 'association', 'email' => 'scoperte@example.test', 'municipality_id' => $municipality->id, 'verification_status' => 'verified']);
        $category = Category::query()->create(['name' => 'Gusto', 'slug' => 'gusto', 'is_active' => true]);

        return [$customer, $organizer, $municipality, $category];
    }

    private function event(OrganizerProfile $organizer, Municipality $municipality, Category $category, $startsAt, int $available = 10): Event
    {
        $event = Event::query()->create(['ulid' => (string) Str::ulid(), 'organizer_profile_id' => $organizer->id, 'municipality_id' => $municipality->id, 'title' => 'Evento '.Str::random(5), 'slug' => 'evento-'.Str::lower(Str::random(8)), 'summary' => 'Evento reale', 'description' => 'Evento reale', 'status' => $available ? EventStatus::Published : EventStatus::SoldOut, 'starts_at' => $startsAt, 'capacity_total' => 10, 'available_total' => $available, 'is_free' => true, 'family_friendly' => true, 'indoor_outdoor' => 'mixed']);
        $event->categories()->sync([$category->id]);

        return $event;
    }
}
