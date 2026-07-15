<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Enums\UserRole;
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

class EventModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unapproved_event_does_not_appear_publicly(): void
    {
        $event = $this->makeEvent(EventStatus::PendingReview);

        $this->getJson('/api/v1/events')->assertOk()->assertJsonMissing(['ulid' => $event->ulid]);
    }

    public function test_admin_approves_event_and_it_appears_publicly(): void
    {
        $event = $this->makeEvent(EventStatus::PendingReview);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/events/{$event->ulid}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->getJson('/api/v1/events')->assertOk()->assertJsonFragment(['ulid' => $event->ulid]);
    }

    public function test_organizer_cannot_update_another_organizers_event(): void
    {
        $event = $this->makeEvent(EventStatus::Draft);
        $otherOrganizer = User::factory()->create(['role' => UserRole::Organizer]);

        $this->actingAs($otherOrganizer, 'sanctum')
            ->patchJson("/api/v1/organizer/events/{$event->ulid}", [])
            ->assertForbidden();
    }

    private function makeEvent(EventStatus $status): Event
    {
        $region = Region::query()->create(['name' => Str::random(8), 'code' => Str::upper(Str::random(3))]);
        $province = Province::query()->create(['region_id' => $region->id, 'name' => Str::random(8), 'code' => Str::upper(Str::random(2))]);
        $municipality = Municipality::query()->create(['province_id' => $province->id, 'name' => 'Campobasso', 'istat_code' => Str::random(6)]);
        $category = Category::query()->create(['name' => Str::random(10), 'slug' => Str::slug(Str::random(10)), 'is_active' => true]);
        $user = User::factory()->create(['role' => UserRole::Organizer]);
        $profile = OrganizerProfile::query()->create([
            'ulid' => (string) Str::ulid(),
            'owner_user_id' => $user->id,
            'name' => 'Org',
            'type' => 'associazione',
            'email' => 'org@example.test',
            'verification_status' => 'verified',
        ]);

        $event = Event::query()->create([
            'ulid' => (string) Str::ulid(),
            'organizer_profile_id' => $profile->id,
            'municipality_id' => $municipality->id,
            'title' => 'Evento test',
            'slug' => 'evento-test-'.Str::random(6),
            'summary' => 'Summary',
            'description' => 'Description',
            'status' => $status,
            'starts_at' => now()->addWeek(),
            'capacity_total' => 10,
            'available_total' => 10,
            'is_free' => true,
            'family_friendly' => true,
            'indoor_outdoor' => 'mixed',
        ]);
        $event->categories()->sync([$category->id]);

        return $event;
    }
}
