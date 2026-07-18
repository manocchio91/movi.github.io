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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicEventSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_suite_uses_the_dedicated_testing_database(): void
    {
        $this->assertSame('app_eventi_testing', DB::scalar('select current_database()'));
    }

    public function test_it_searches_case_insensitively_in_title_and_description(): void
    {
        [$category, $otherCategory] = $this->fixtures();
        $titleMatch = $this->event('Degustazione in Vigna', 'Una serata speciale', $category);
        $descriptionMatch = $this->event('Sapori locali', 'Percorso nella VIGNA storica', $category);
        $this->event('Laboratorio del pane', 'Attivita per famiglie', $otherCategory);

        $this->getJson('/api/v1/events?q=vigna')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['ulid' => $titleMatch->ulid])
            ->assertJsonFragment(['ulid' => $descriptionMatch->ulid]);
    }

    public function test_it_returns_an_empty_page_when_search_has_no_matches(): void
    {
        [$category] = $this->fixtures();
        $this->event('Evento esistente', 'Descrizione esistente', $category);

        $this->getJson('/api/v1/events?q=inesistente')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('total', 0);
    }

    public function test_search_combines_with_existing_filters(): void
    {
        [$category, $otherCategory] = $this->fixtures();
        $match = $this->event('Cena in vigna', 'Esperienza serale', $category, true);
        $this->event('Cena in vigna alternativa', 'Esperienza serale', $otherCategory, true);
        $this->event('Cena in vigna completa', 'Esperienza serale', $category, false);

        $this->getJson("/api/v1/events?q=cena&category_id={$category->id}&available=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ulid', $match->ulid);
    }

    public function test_missing_or_blank_search_preserves_the_unfiltered_behavior(): void
    {
        [$category] = $this->fixtures();
        $this->event('Primo evento', 'Prima descrizione', $category);
        $this->event('Secondo evento', 'Seconda descrizione', $category);

        $withoutSearch = $this->getJson('/api/v1/events')->assertOk()->json();
        $blankSearch = $this->getJson('/api/v1/events?q=%20%20%20')->assertOk()->json();

        $this->assertSame(2, $withoutSearch['total']);
        $this->assertSame($withoutSearch['data'], $blankSearch['data']);
    }

    /** @return array{Category, Category} */
    private function fixtures(): array
    {
        $region = Region::query()->create(['name' => 'Molise', 'code' => 'MOL']);
        $province = Province::query()->create(['region_id' => $region->id, 'name' => 'Campobasso', 'code' => 'CB']);
        $municipality = Municipality::query()->create(['province_id' => $province->id, 'name' => 'Campobasso', 'istat_code' => '070006']);
        $owner = User::factory()->create(['role' => UserRole::Organizer, 'status' => 'active']);
        $this->profile = OrganizerProfile::query()->create([
            'ulid' => (string) Str::ulid(),
            'owner_user_id' => $owner->id,
            'name' => 'Organizzatore ricerca',
            'type' => 'associazione',
            'email' => 'search@example.test',
            'municipality_id' => $municipality->id,
            'verification_status' => 'verified',
        ]);
        $this->municipality = $municipality;

        return [
            Category::query()->create(['name' => 'Enogastronomia', 'slug' => 'enogastronomia', 'is_active' => true]),
            Category::query()->create(['name' => 'Famiglie', 'slug' => 'famiglie', 'is_active' => true]),
        ];
    }

    private OrganizerProfile $profile;

    private Municipality $municipality;

    private function event(string $title, string $description, Category $category, bool $available = true): Event
    {
        $event = Event::query()->create([
            'ulid' => (string) Str::ulid(),
            'organizer_profile_id' => $this->profile->id,
            'municipality_id' => $this->municipality->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(6),
            'summary' => $description,
            'description' => $description,
            'status' => EventStatus::Published,
            'starts_at' => now()->addDays($available ? 2 : 3),
            'capacity_total' => 20,
            'available_total' => $available ? 10 : 0,
            'is_free' => false,
            'family_friendly' => true,
            'indoor_outdoor' => 'mixed',
        ]);
        $event->categories()->attach($category);

        return $event;
    }
}
