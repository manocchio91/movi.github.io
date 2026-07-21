<?php

namespace Tests\Feature;

use App\Actions\Auth\CreateOrganizerProfile;
use App\Enums\UserRole;
use App\Models\OrganizerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AuthRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_registration_persists_role_returns_token_and_grants_customer_access(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Cliente Reale',
            'email' => 'cliente.reale@example.test',
            'password' => 'password-sicura',
            'password_confirmation' => 'password-sicura',
            'role' => 'customer',
        ])->assertCreated()
            ->assertJsonPath('user.role', 'customer')
            ->assertJsonPath('user.status', 'active')
            ->assertJsonPath('redirect_to', '/customer/dashboard')
            ->assertJsonStructure(['user', 'token', 'redirect_to']);

        $this->assertDatabaseHas('users', [
            'email' => 'cliente.reale@example.test',
            'role' => UserRole::Customer->value,
        ]);

        $this->withToken($response->json('token'))
            ->getJson('/api/v1/customer/dashboard')
            ->assertOk();
    }

    public function test_registration_rejects_duplicate_email_and_wrong_password_confirmation(): void
    {
        User::factory()->create(['email' => 'duplicata@example.test']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Duplicato',
            'email' => 'duplicata@example.test',
            'password' => 'password-sicura',
            'password_confirmation' => 'password-sicura',
            'role' => 'customer',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Conferma Errata',
            'email' => 'conferma@example.test',
            'password' => 'password-sicura',
            'password_confirmation' => 'password-diversa',
            'role' => 'customer',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_organizer_registration_atomically_creates_a_pending_profile(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Referente Organizer',
            'email' => 'nuovo.organizer@example.test',
            'password' => 'password-sicura',
            'password_confirmation' => 'password-sicura',
            'role' => 'organizer',
            'organization_name' => 'Cantina Reale',
            'organization_type' => 'azienda',
        ])->assertCreated()
            ->assertJsonPath('user.role', 'organizer')
            ->assertJsonPath('user.organizer_profile.name', 'Cantina Reale')
            ->assertJsonPath('user.organizer_profile.verification_status', 'pending')
            ->assertJsonPath('redirect_to', '/organizer/pending')
            ->assertJsonStructure(['token']);

        $user = User::query()->where('email', 'nuovo.organizer@example.test')->firstOrFail();
        $this->assertSame(UserRole::Organizer, $user->role);
        $this->assertDatabaseHas('organizer_profiles', [
            'owner_user_id' => $user->id,
            'name' => 'Cantina Reale',
            'verification_status' => 'pending',
        ]);

        $this->withToken($response->json('token'))
            ->getJson('/api/v1/organizer/dashboard')
            ->assertForbidden();
    }

    public function test_pending_organizer_can_login_and_verified_organizer_can_access_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Organizer,
            'status' => 'active',
        ]);
        $profile = OrganizerProfile::query()->create([
            'ulid' => '01J00000000000000000000000',
            'owner_user_id' => $user->id,
            'name' => 'Organizer QA',
            'type' => 'azienda',
            'email' => $user->email,
            'verification_status' => 'pending',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('user.organizer_profile.verification_status', 'pending')
            ->assertJsonPath('redirect_to', '/organizer/pending');

        $profile->forceFill(['verification_status' => 'verified', 'verified_at' => now()])->save();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('redirect_to', '/organizer/dashboard');

        $this->withToken($response->json('token'))
            ->getJson('/api/v1/organizer/dashboard')
            ->assertOk();
    }

    public function test_login_rejects_bad_credentials_and_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create(['role' => UserRole::Customer]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'errata',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Credenziali non valide.');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('user.role', 'customer');

        $token = $login->json('token');
        $this->app['auth']->guard('web')->logout();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_organizer_registration_rolls_back_user_when_profile_creation_fails(): void
    {
        $this->mock(CreateOrganizerProfile::class, function ($mock): void {
            $mock->shouldReceive('execute')->once()->andThrow(new RuntimeException('Profile creation failed.'));
        });
        $this->withoutExceptionHandling();

        try {
            $this->postJson('/api/v1/auth/register', [
                'name' => 'Rollback Organizer',
                'email' => 'rollback@example.test',
                'password' => 'password-sicura',
                'password_confirmation' => 'password-sicura',
                'role' => 'organizer',
                'organization_name' => 'Profilo Fallito',
                'organization_type' => 'azienda',
            ]);

            $this->fail('La creazione del profilo doveva fallire.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Profile creation failed.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('users', ['email' => 'rollback@example.test']);
        $this->assertDatabaseCount('organizer_profiles', 0);
    }
}
