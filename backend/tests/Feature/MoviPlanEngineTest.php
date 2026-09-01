<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\ActivityService;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\OrganizerProfile;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MoviPlanEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_bounded_temporally_and_geographically_compatible_real_plans(): void
    {
        [$customer, $organizer, $municipality] = $this->domain();
        $date = now()->addDay();
        $this->activity($organizer, $municipality, 'Aperitivo', '18:00', 60, $date->dayOfWeekIso);
        $this->activity($organizer, $municipality, 'Cena', '20:00', 90, $date->dayOfWeekIso);
        $event = Event::query()->create(['ulid' => (string) Str::ulid(), 'organizer_profile_id' => $organizer->id, 'municipality_id' => $municipality->id, 'title' => 'Concerto', 'slug' => 'concerto-plan', 'summary' => 'Live', 'description' => 'Live', 'status' => EventStatus::Published, 'starts_at' => $date->copy()->setTime(22, 30), 'ends_at' => $date->copy()->setTime(23, 30), 'capacity_total' => 50, 'available_total' => 20, 'is_free' => false, 'family_friendly' => false, 'indoor_outdoor' => 'indoor']);
        $event->ticketTypes()->create(['ulid' => (string) Str::ulid(), 'name' => 'Ingresso', 'audience' => 'generic', 'price_cents' => 1500, 'is_active' => true, 'booked_quantity' => 0]);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/v1/plans/generate', ['when' => 'tomorrow', 'start_time' => '17:00', 'end_time' => '23:59', 'latitude' => 41.56, 'longitude' => 14.66, 'radius_km' => 20, 'budget_cents' => 3000])->assertOk();
        $plans = $response->json('data');
        $this->assertLessThanOrEqual(3, count($plans));
        $this->assertNotEmpty($plans);
        $this->assertTrue(collect($plans)->contains(fn (array $plan) => $plan['number_of_steps'] >= 2));
        foreach ($plans as $plan) {
            foreach (array_slice($plan['steps'], 1) as $index => $step) {
                $this->assertGreaterThanOrEqual(strtotime($plan['steps'][$index]['end']) + 30 * 60, strtotime($step['start']));
            }
        }
        $this->assertDatabaseHas('analytics_events', ['type' => 'plan_requested']);
        $this->assertDatabaseHas('analytics_events', ['type' => 'plan_generated']);
    }

    public function test_budget_is_partial_without_invented_activity_price_and_revalidation_reports_failure(): void
    {
        [$customer, $organizer, $municipality] = $this->domain();
        $date = now()->addDay();
        [$activity, $service] = $this->activity($organizer, $municipality, 'Cena vera', '20:00', 90, $date->dayOfWeekIso);
        $plan = $this->actingAs($customer, 'sanctum')->postJson('/api/v1/plans/generate', ['when' => 'tomorrow', 'type' => 'activity', 'budget_cents' => 5000])->assertOk()->json('data.0');
        $this->assertNull($plan['known_total_price_cents']);
        $this->assertSame('partial', $plan['budget_verification']);
        $service->availabilityRules()->update(['active' => false]);
        $this->postJson('/api/v1/plans/revalidate', ['steps' => [['entity_type' => 'activity', 'entity_id' => $activity->ulid, 'service_id' => $service->ulid, 'start' => $plan['steps'][0]['start']]]])
            ->assertOk()->assertJsonPath('data.available', false)->assertJsonPath('data.unavailable_steps.0', $activity->ulid);
        $this->assertDatabaseHas('analytics_events', ['type' => 'plan_unavailable_on_revalidation']);
    }

    private function domain(): array
    {
        $customer = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active']);
        $owner = User::factory()->create(['role' => UserRole::Organizer, 'status' => 'active']);
        $region = Region::query()->create(['name' => 'Molise', 'code' => 'MOL']);
        $province = Province::query()->create(['region_id' => $region->id, 'name' => 'Campobasso', 'code' => 'CB']);
        $municipality = Municipality::query()->create(['province_id' => $province->id, 'name' => 'Campobasso', 'istat_code' => '070006', 'latitude' => 41.5603, 'longitude' => 14.6627]);
        $organizer = OrganizerProfile::query()->create(['ulid' => (string) Str::ulid(), 'owner_user_id' => $owner->id, 'name' => 'Plan', 'type' => 'association', 'email' => 'plan@example.test', 'municipality_id' => $municipality->id, 'verification_status' => 'verified']);

        return [$customer, $organizer, $municipality];
    }

    private function activity(OrganizerProfile $organizer, Municipality $municipality, string $name, string $time, int $duration, int $weekday): array
    {
        $activity = Activity::query()->create(['ulid' => (string) Str::ulid(), 'organizer_profile_id' => $organizer->id, 'municipality_id' => $municipality->id, 'name' => $name, 'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)), 'type' => ActivityType::Restaurant, 'status' => ActivityStatus::Published, 'booking_enabled' => true]);
        $service = ActivityService::query()->create(['ulid' => (string) Str::ulid(), 'activity_id' => $activity->id, 'name' => $name, 'type' => 'experience', 'active' => true, 'booking_enabled' => true, 'min_party_size' => 1, 'max_party_size' => 8, 'minimum_advance_minutes' => 0, 'indicative_duration_minutes' => $duration, 'capacity_mode' => 'covers']);
        $service->availabilityRules()->create(['weekday' => $weekday, 'start_time' => $time, 'capacity' => 10, 'active' => true]);

        return [$activity, $service];
    }
}
