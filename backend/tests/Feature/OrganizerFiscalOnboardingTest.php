<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\OperationalMessage;
use App\Models\Municipality;
use App\Models\OrganizerProfile;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizerFiscalOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_saves_partial_profile_and_normalizes_fiscal_values(): void
    {
        [$user, $profile, $municipality] = $this->organizer('incomplete');
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/organizer/profile', [
            'municipality_id' => $municipality->id,
            'pec' => '  FISCALE@PEC.EXAMPLE.TEST ',
            'vat_number' => 'IT 000-000-00000',
            'sdi_code' => 'abc12d3',
        ])->assertOk()
            ->assertJsonPath('data.pec', 'fiscale@pec.example.test')
            ->assertJsonPath('data.vat_number', 'IT00000000000')
            ->assertJsonPath('data.sdi_code', 'ABC12D3');

        $this->assertSame('incomplete', $profile->refresh()->verification_status);
    }

    public function test_validation_rejects_invalid_pec_vat_sdi_and_unsafe_links(): void
    {
        [$user] = $this->organizer('incomplete');
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/organizer/profile', [
            'pec' => 'non-email',
            'vat_number' => '123',
            'sdi_code' => 'ABC-123',
            'website_url' => 'javascript:alert(1)',
            'verification_status' => 'verified',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['pec', 'vat_number', 'sdi_code', 'website_url']);
    }

    public function test_submit_requires_complete_profile_and_accepts_zero_sdi(): void
    {
        [$user, $profile, $municipality] = $this->organizer('incomplete');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/organizer/profile/submit')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address', 'street_number', 'postal_code', 'municipality_id', 'province', 'vat_number', 'pec', 'sdi_code']);

        $this->patchJson('/api/v1/organizer/profile', $this->completePayload($municipality))->assertOk();
        $this->postJson('/api/v1/organizer/profile/submit')->assertOk()
            ->assertJsonPath('data.verification_status', 'pending')
            ->assertJsonPath('data.sdi_code', '0000000');

        $this->assertNotNull($profile->refresh()->submitted_at);
        $this->patchJson('/api/v1/organizer/profile', ['name' => 'Non consentito'])->assertStatus(409);
    }

    public function test_admin_lists_details_and_role_guards_requests(): void
    {
        [$organizer, $profile, $municipality] = $this->organizer('pending');
        $profile->update([...$this->completePayload($municipality), 'submitted_at' => now()]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/organizers?status=pending&per_page=10')->assertOk()
            ->assertJsonPath('pending_count', 1)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.owner.email', $organizer->email)
            ->assertJsonPath('data.data.0.pec', 'fiscale@pec.example.test');
        $this->getJson("/api/v1/admin/organizers/{$profile->ulid}")->assertOk()
            ->assertJsonPath('data.sdi_code', '0000000');

        foreach ([UserRole::Customer, UserRole::Organizer] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));
            $this->getJson('/api/v1/admin/organizers')->assertForbidden();
        }
    }

    public function test_admin_can_request_changes_then_organizer_corrects_and_resubmits(): void
    {
        [$organizer, $profile, $municipality] = $this->organizer('pending');
        $profile->update([...$this->completePayload($municipality), 'submitted_at' => now()]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/organizers/{$profile->ulid}/request-changes", ['reason' => 'Correggere il civico.'])
            ->assertOk()->assertJsonPath('data.verification_status', 'changes_requested');

        Sanctum::actingAs($organizer);
        $this->getJson('/api/v1/organizer/profile')->assertJsonPath('data.review_notes', 'Correggere il civico.');
        $this->patchJson('/api/v1/organizer/profile', ['street_number' => '12/A'])->assertOk();
        $this->postJson('/api/v1/organizer/profile/submit')->assertOk()
            ->assertJsonPath('data.verification_status', 'pending')
            ->assertJsonPath('data.review_notes', null);
    }

    public function test_admin_approval_and_rejection_require_valid_transitions_and_reason(): void
    {
        Mail::fake();
        [$organizer, $profile] = $this->organizer('pending');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/organizers/{$profile->ulid}/reject", [])->assertUnprocessable();
        $this->postJson("/api/v1/admin/organizers/{$profile->ulid}/approve")->assertOk()
            ->assertJsonPath('data.verification_status', 'verified');
        Mail::assertQueued(OperationalMessage::class, fn (OperationalMessage $mail) => $mail->hasTo($organizer->email)
            && $mail->envelope()->subject === 'Profilo Organizer approvato'
            && str_contains($mail->render(), 'funzionalità Organizzatore di MòVi'));
        $this->assertDatabaseCount('activities', 1);
        $this->postJson("/api/v1/admin/organizers/{$profile->ulid}/approve")->assertStatus(409);
        Mail::assertQueued(OperationalMessage::class, 1);

        Sanctum::actingAs($organizer);
        $this->getJson('/api/v1/organizer/dashboard')->assertOk();

        [, $rejected] = $this->organizer('pending');
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/organizers/{$rejected->ulid}/reject", ['reason' => 'Dati non verificabili.'])
            ->assertOk()->assertJsonPath('data.review_notes', 'Dati non verificabili.');
        Sanctum::actingAs($rejected->owner);
        $this->getJson('/api/v1/organizer/dashboard')->assertForbidden();
    }

    public function test_email_provider_failure_does_not_rollback_organizer_approval(): void
    {
        [, $profile] = $this->organizer('pending');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Provider unavailable'));

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/organizers/{$profile->ulid}/approve")
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'verified');

        $this->assertSame('verified', $profile->refresh()->verification_status);
    }

    /** @return array{User, OrganizerProfile, Municipality} */
    private function organizer(string $status): array
    {
        $region = Region::query()->create(['name' => fake()->unique()->state(), 'code' => strtoupper(fake()->unique()->lexify('???'))]);
        $province = Province::query()->create(['region_id' => $region->id, 'name' => fake()->unique()->city(), 'code' => strtoupper(fake()->unique()->lexify('??'))]);
        $municipality = Municipality::query()->create(['province_id' => $province->id, 'name' => fake()->city(), 'istat_code' => fake()->unique()->numerify('######')]);
        $user = User::factory()->create(['role' => UserRole::Organizer]);
        $profile = OrganizerProfile::query()->create([
            'ulid' => (string) str()->ulid(), 'owner_user_id' => $user->id, 'name' => 'Impresa QA',
            'type' => 'azienda', 'email' => $user->email, 'country' => 'Italia', 'verification_status' => $status,
        ]);

        return [$user, $profile, $municipality];
    }

    /** @return array<string, mixed> */
    private function completePayload(Municipality $municipality): array
    {
        return [
            'name' => 'Impresa QA', 'address' => 'Via Roma', 'street_number' => '10', 'postal_code' => '86100',
            'municipality_id' => $municipality->id, 'province' => 'CB', 'country' => 'Italia',
            'vat_number' => 'IT00000000000', 'pec' => 'fiscale@pec.example.test', 'sdi_code' => '0000000',
        ];
    }
}
