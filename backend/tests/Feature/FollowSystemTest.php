<?php

namespace Tests\Feature;

use App\Actions\Notifications\NotifyInterestedCustomers;
use App\Enums\UserRole;
use App\Jobs\NotifyInterestedCustomersJob;
use App\Models\Category;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\NotificationPreference;
use App\Models\OrganizerProfile;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FollowSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_follow_idempotently_see_status_and_unfollow_idempotently(): void
    {
        [$customer, , $profile] = $this->domain();

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/customer/organizers/{$profile->ulid}/follow")
            ->assertOk()->assertJsonPath('data.created', true);
        $this->postJson("/api/v1/customer/organizers/{$profile->ulid}/follow")
            ->assertOk()->assertJsonPath('data.created', false);

        $this->assertDatabaseCount('organizer_followers', 1);
        $this->assertDatabaseHas('analytics_events', ['type' => 'organizer_followed', 'organizer_profile_id' => $profile->id]);
        $this->getJson("/api/v1/organizers/{$profile->ulid}")
            ->assertOk()
            ->assertJsonPath('data.is_following', true)
            ->assertJsonPath('data.followers_count', 1);

        $this->deleteJson("/api/v1/customer/organizers/{$profile->ulid}/follow")
            ->assertOk()->assertJsonPath('data.removed', true);
        $this->deleteJson("/api/v1/customer/organizers/{$profile->ulid}/follow")
            ->assertOk()->assertJsonPath('data.removed', false);
        $this->assertDatabaseCount('organizer_followers', 0);
        $this->assertDatabaseHas('analytics_events', ['type' => 'organizer_unfollowed', 'organizer_profile_id' => $profile->id]);
    }

    public function test_follow_requires_customer_authentication(): void
    {
        [, , $profile] = $this->domain();

        $this->postJson("/api/v1/customer/organizers/{$profile->ulid}/follow")->assertUnauthorized();
    }

    public function test_following_and_follower_responses_are_privacy_safe(): void
    {
        [$customer, $organizer, $profile] = $this->domain();
        $customer->followedOrganizers()->attach($profile->id);

        $following = $this->actingAs($customer, 'sanctum')->getJson('/api/v1/customer/following')->assertOk();
        $following->assertJsonPath('data.0.ulid', $profile->ulid)
            ->assertJsonMissing(['email' => $profile->email]);

        $followers = $this->actingAs($organizer, 'sanctum')->getJson('/api/v1/organizer/followers')->assertOk();
        $followers->assertJsonPath('data.0.display_name', $customer->name)
            ->assertJsonMissing(['email' => $customer->email])
            ->assertJsonMissingPath('data.0.id');
    }

    public function test_followed_event_notifications_respect_preference_and_are_deduplicated(): void
    {
        [$customer, , $profile, $event] = $this->domain();
        $disabled = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active']);
        $customer->followedOrganizers()->attach($profile->id);
        $disabled->followedOrganizers()->attach($profile->id);
        NotificationPreference::query()->create([
            'user_id' => $customer->id,
            'internal_enabled' => true,
            'email_enabled' => false,
            'promotional_enabled' => false,
            'operational_enabled' => true,
            'followed_organizer_events_enabled' => true,
            'frequency' => 'immediate',
        ]);
        NotificationPreference::query()->create([
            'user_id' => $disabled->id,
            'internal_enabled' => true,
            'email_enabled' => false,
            'promotional_enabled' => true,
            'operational_enabled' => true,
            'followed_organizer_events_enabled' => false,
            'frequency' => 'immediate',
        ]);

        (new NotifyInterestedCustomersJob($event->id))->handle(app(NotifyInterestedCustomers::class));
        (new NotifyInterestedCustomersJob($event->id))->handle(app(NotifyInterestedCustomers::class));

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', ['user_id' => $customer->id, 'kind' => 'event_published']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $disabled->id]);
    }

    private function domain(): array
    {
        $customer = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active']);
        $organizer = User::factory()->create(['role' => UserRole::Organizer, 'status' => 'active']);
        $region = Region::query()->create(['name' => 'Molise '.Str::random(4), 'code' => Str::upper(Str::random(3))]);
        $province = Province::query()->create(['region_id' => $region->id, 'name' => 'Campobasso', 'code' => Str::upper(Str::random(2))]);
        $municipality = Municipality::query()->create(['province_id' => $province->id, 'name' => 'Campobasso', 'istat_code' => Str::random(6)]);
        $profile = OrganizerProfile::query()->create([
            'ulid' => (string) Str::ulid(), 'owner_user_id' => $organizer->id,
            'name' => 'Cantina Test', 'type' => 'winery', 'email' => 'private@example.test',
            'municipality_id' => $municipality->id, 'verification_status' => 'verified',
        ]);
        $category = Category::query()->create(['name' => 'Vino '.Str::random(4), 'slug' => 'vino-'.Str::lower(Str::random(5)), 'is_active' => true]);
        $event = Event::query()->create([
            'ulid' => (string) Str::ulid(), 'organizer_profile_id' => $profile->id,
            'municipality_id' => $municipality->id, 'title' => 'Degustazione',
            'slug' => 'degustazione-'.Str::lower(Str::random(5)), 'summary' => 'Evento test',
            'description' => 'Evento test follow', 'status' => 'published', 'starts_at' => now()->addWeek(),
            'capacity_total' => 10, 'available_total' => 10, 'is_free' => true,
            'family_friendly' => false, 'indoor_outdoor' => 'indoor',
        ]);
        $event->categories()->attach($category->id);

        return [$customer, $organizer, $profile, $event];
    }
}
