<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\OrganizerProfile;
use App\Models\User;
use Database\Seeders\StagingBetaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class StagingBetaSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_beta_seeder_refuses_non_staging_environment(): void
    {
        $this->expectException(RuntimeException::class);
        $this->seed(StagingBetaSeeder::class);
    }

    public function test_beta_seeder_requires_external_credentials_and_is_idempotent(): void
    {
        $this->app->detectEnvironment(fn (): string => 'staging');
        foreach ([
            'BETA_ADMIN_EMAIL' => 'admin-beta@example.test', 'BETA_ADMIN_PASSWORD' => 'Admin-secret-123',
            'BETA_ORGANIZER_EMAIL' => 'organizer-beta@example.test', 'BETA_ORGANIZER_PASSWORD' => 'Organizer-secret-123',
            'BETA_CUSTOMER_EMAIL' => 'customer-beta@example.test', 'BETA_CUSTOMER_PASSWORD' => 'Customer-secret-123',
        ] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }

        $this->seed(StagingBetaSeeder::class);
        $this->seed(StagingBetaSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertSame(1, User::query()->where('role', UserRole::Admin)->count());
        $this->assertSame(1, User::query()->where('role', UserRole::Organizer)->count());
        $this->assertSame(1, User::query()->where('role', UserRole::Customer)->count());
        $this->assertSame('verified', OrganizerProfile::query()->sole()->verification_status);
        $this->assertSame(5, Category::query()->count());
    }
}
