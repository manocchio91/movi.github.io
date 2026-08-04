<?php

namespace Tests\Feature;

use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MunicipalityPostalCodeLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_postal_code_with_one_municipality_returns_only_required_fields(): void
    {
        $this->municipality('Campobasso', 'CB', '86100');

        $this->getJson('/api/v1/locations/municipalities?postal_code=86100')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Campobasso')->assertJsonPath('data.0.province', 'CB')
            ->assertJsonPath('data.0.postal_code', '86100')
            ->assertJsonStructure(['data' => [['id', 'name', 'province', 'postal_code']]])
            ->assertJsonMissingPath('data.0.istat_code');
    }

    public function test_postal_code_with_multiple_municipalities_returns_all_matches(): void
    {
        $this->municipality('Comune Alfa', 'CB', '86010');
        $this->municipality('Comune Beta', 'CB', '86010');

        $this->getJson('/api/v1/locations/municipalities?postal_code=86010')
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Comune Alfa')->assertJsonPath('data.1.name', 'Comune Beta');
    }

    public function test_unknown_postal_code_returns_empty_data(): void
    {
        $this->getJson('/api/v1/locations/municipalities?postal_code=99999')
            ->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_invalid_postal_code_is_rejected(): void
    {
        foreach (['', '8610', '8610A', '861000'] as $postalCode) {
            $this->getJson('/api/v1/locations/municipalities?postal_code='.$postalCode)
                ->assertUnprocessable()->assertJsonValidationErrors('postal_code');
        }
    }

    private function municipality(string $name, string $provinceCode, string $postalCode): Municipality
    {
        $region = Region::query()->firstOrCreate(['code' => 'TST'], ['name' => 'Test']);
        $province = Province::query()->firstOrCreate(['code' => $provinceCode], ['region_id' => $region->id, 'name' => 'Provincia '.$provinceCode]);

        return Municipality::query()->create(['province_id' => $province->id, 'name' => $name, 'istat_code' => fake()->unique()->numerify('######'), 'postal_code' => $postalCode]);
    }
}
