<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\OrganizerProfile;
use App\Models\User;
use App\Services\OperationalEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MvpProfilesCommercialOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_profile_and_admin_search_use_real_data_and_public_identifiers(): void
    {
        $customer = User::factory()->create(['name' => 'Cliente Ricercabile', 'email' => 'cliente@example.test']);
        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/me/profile')->assertOk()
            ->assertJsonPath('data.ulid', $customer->ulid)
            ->assertJsonPath('data.bookings_count', 0);
        $this->patchJson('/api/v1/me/profile', ['name' => 'Cliente Aggiornato', 'email' => 'aggiornato@example.test'])
            ->assertOk()->assertJsonPath('data.name', 'Cliente Aggiornato');
        $this->putJson('/api/v1/me/password', [
            'current_password' => 'password', 'password' => 'Nuova-password-123',
            'password_confirmation' => 'Nuova-password-123',
        ])->assertOk();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/search?q=Aggiornato')->assertOk()
            ->assertJsonPath('data.customers.0.ulid', $customer->ulid)
            ->assertJsonMissingPath('data.customers.0.id');
        $this->getJson('/api/v1/admin/customers/'.$customer->ulid)->assertOk()
            ->assertJsonPath('data.email', 'aggiornato@example.test');
    }

    public function test_admin_manages_plans_and_commercial_agreement_without_payments(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $organizer = $this->organizer('verified');
        Sanctum::actingAs($admin);

        $plan = $this->postJson('/api/v1/admin/plans', [
            'name' => 'Pay per booking', 'description' => 'Accordo manuale',
            'billing_model' => 'pay_per_booking', 'billing_interval' => 'monthly',
            'monthly_price_cents' => 1900, 'booking_fee_cents' => 75, 'is_active' => true,
        ])->assertCreated()->json('data');

        $this->putJson('/api/v1/admin/organizers/'.$organizer->ulid.'/plan', [
            'plan_ulid' => $plan['ulid'], 'status' => 'active',
            'agreement_starts_at' => '2026-08-01', 'agreement_ends_at' => '2027-07-31',
            'agreement_notes' => 'Accordo firmato offline.',
        ])->assertOk()
            ->assertJsonPath('data.plan.billing_model', 'pay_per_booking')
            ->assertJsonPath('data.agreement_notes', 'Accordo firmato offline.');
    }

    public function test_organizer_upload_is_validated_and_public_profile_never_exposes_fiscal_data(): void
    {
        Storage::fake('public');
        $profile = $this->organizer('verified');
        Sanctum::actingAs($profile->owner);

        $this->patchJson('/api/v1/organizer/profile', ['description' => 'Descrizione pubblica', 'vat_number' => 'IT00000000000'])
            ->assertStatus(409);
        $this->patchJson('/api/v1/organizer/profile', ['description' => 'Descrizione pubblica'])->assertOk();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $this->postJson('/api/v1/organizer/profile/images', ['logo' => UploadedFile::fake()->createWithContent('logo.png', $png)])
            ->assertOk()->assertJsonStructure(['data' => ['logo_url']]);
        $this->postJson('/api/v1/organizer/profile/images', ['logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')])
            ->assertUnprocessable();

        $this->getJson('/api/v1/organizers/'.$profile->ulid)->assertOk()
            ->assertJsonPath('data.description', 'Descrizione pubblica')
            ->assertJsonMissingPath('data.vat_number')
            ->assertJsonMissingPath('data.pec')
            ->assertJsonMissingPath('data.address');
    }

    public function test_operational_email_uses_mail_transport_that_can_be_faked(): void
    {
        Mail::fake();
        $profile = $this->organizer('verified')->load('owner');

        app(OperationalEmailService::class)->organizerReviewed($profile);

        Mail::assertSentCount(1);
    }

    private function organizer(string $status): OrganizerProfile
    {
        $owner = User::factory()->create(['role' => UserRole::Organizer]);

        return OrganizerProfile::query()->create([
            'ulid' => (string) str()->ulid(), 'owner_user_id' => $owner->id,
            'name' => 'Organizer MVP', 'type' => 'azienda', 'email' => $owner->email,
            'country' => 'Italia', 'vat_number' => 'IT00000000000',
            'pec' => 'fiscale@example.test', 'sdi_code' => '0000000',
            'verification_status' => $status,
        ]);
    }
}
