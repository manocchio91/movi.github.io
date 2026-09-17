<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Notification;
use App\Models\OrganizerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizerProfileApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_organizer_submits_pending_changes_without_changing_public_data_and_retry_is_deduplicated(): void
    {
        [$owner, $profile] = $this->verifiedOrganizer();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Sanctum::actingAs($owner);

        $this->patchJson('/api/v1/organizer/profile', ['name' => 'Nome Proposto', 'description' => 'Nuova descrizione'])
            ->assertOk()->assertJsonPath('data.name', 'Nome Approvato')->assertJsonPath('data.profile_change_status', 'pending');
        $requestUlid = $profile->refresh()->profile_change_request_ulid;
        $this->patchJson('/api/v1/organizer/profile', ['phone' => '+39 333 1234567'])->assertOk();

        $this->assertSame($requestUlid, $profile->refresh()->profile_change_request_ulid);
        $this->assertSame('Nome Approvato', $profile->name);
        $this->assertSame('Nome Proposto', $profile->pending_changes['name']);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame($admin->id, Notification::query()->firstOrFail()->user_id);
        $this->getJson('/api/v1/organizers/'.$profile->ulid)->assertOk()->assertJsonPath('data.name', 'Nome Approvato')->assertJsonMissingPath('data.pending_changes');
    }

    public function test_admin_approves_once_and_organizer_is_notified(): void
    {
        [$owner, $profile] = $this->verifiedOrganizer();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Sanctum::actingAs($owner);
        $this->patchJson('/api/v1/organizer/profile', ['name' => 'Nome Pubblicato'])->assertOk();

        Sanctum::actingAs($admin);
        $url = '/api/v1/admin/organizers/'.$profile->ulid.'/profile-changes/approve';
        $this->postJson($url)->assertOk()->assertJsonPath('data.name', 'Nome Pubblicato')->assertJsonPath('data.profile_change_status', 'approved');
        $this->postJson($url)->assertConflict();

        $this->assertDatabaseHas('notifications', ['user_id' => $owner->id, 'kind' => 'organizer_profile_change_decision']);
    }

    public function test_admin_rejects_changes_with_reason_and_non_admin_is_forbidden(): void
    {
        [$owner, $profile] = $this->verifiedOrganizer();
        Sanctum::actingAs($owner);
        $this->patchJson('/api/v1/organizer/profile', ['name' => 'Nome Respinto'])->assertOk();
        $url = '/api/v1/admin/organizers/'.$profile->ulid.'/profile-changes/reject';
        $this->postJson($url, ['reason' => 'No'])->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->postJson($url, [])->assertUnprocessable();
        $this->postJson($url, ['reason' => 'Denominazione non verificabile.'])->assertOk()->assertJsonPath('data.profile_change_status', 'rejected');
        $this->assertSame('Nome Approvato', $profile->refresh()->name);
        $this->assertSame('Denominazione non verificabile.', $profile->profile_change_review_notes);
    }

    public function test_initial_approval_creates_exactly_one_associated_activity(): void
    {
        [$owner, $profile] = $this->verifiedOrganizer('pending');
        $profile->forceFill(['verification_status' => 'pending'])->save();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/organizers/'.$profile->ulid.'/approve')->assertOk();
        $this->postJson('/api/v1/admin/organizers/'.$profile->ulid.'/approve')->assertConflict();
        $this->assertSame(1, Activity::query()->where('organizer_profile_id', $profile->id)->count());
        $this->assertDatabaseHas('activities', ['organizer_profile_id' => $profile->id, 'status' => 'published']);
    }

    public function test_legacy_verified_organizer_activity_backfill_is_idempotent(): void
    {
        [, $profile] = $this->verifiedOrganizer();
        $migrationPath = database_path('migrations/2026_09_17_000002_backfill_verified_organizer_activities.php');

        (require $migrationPath)->up();
        (require $migrationPath)->up();

        $this->assertSame(1, Activity::query()->where('organizer_profile_id', $profile->id)->count());
        $this->assertDatabaseHas('activities', [
            'organizer_profile_id' => $profile->id,
            'status' => 'published',
            'booking_enabled' => false,
        ]);
    }

    /** @return array{User, OrganizerProfile} */
    private function verifiedOrganizer(string $status = 'verified'): array
    {
        $owner = User::factory()->create(['role' => UserRole::Organizer]);
        $profile = OrganizerProfile::query()->create([
            'ulid' => (string) str()->ulid(), 'owner_user_id' => $owner->id, 'name' => 'Nome Approvato',
            'type' => 'ristorante', 'description' => 'Descrizione approvata', 'email' => $owner->email,
            'country' => 'Italia', 'verification_status' => $status, 'verified_at' => $status === 'verified' ? now() : null,
        ]);

        return [$owner, $profile];
    }
}
