<?php

namespace Tests\Feature;

use Database\Seeders\MoliseGeographySeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StructuralDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_structural_seeders_are_complete_and_idempotent_without_demo_users(): void
    {
        $this->seed([MoliseGeographySeeder::class, SubscriptionPlanSeeder::class]);
        $this->seed([MoliseGeographySeeder::class, SubscriptionPlanSeeder::class]);

        $this->assertDatabaseCount('municipalities', 136);
        $this->assertDatabaseCount('subscription_plans', 4);
        $this->assertSame(13, DB::table('subscription_features')->count());
        $this->assertDatabaseCount('users', 0);
        $this->assertSame(0, DB::table('municipalities')->select('istat_code')->groupBy('istat_code')->havingRaw('count(*) > 1')->count());
    }
}
