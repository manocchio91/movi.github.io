<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\ActivityService;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Municipality;
use App\Models\OrganizerProfile;
use App\Models\Province;
use App\Models\Region;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Menu\MenuImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

class MenuPreOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_is_hidden_owner_can_publish_and_allergens_are_never_inferred(): void
    {
        [$customer, $owner, $activity, $service, $menu, $item] = $this->domain();
        $this->actingAs($customer, 'sanctum')->getJson("/api/v1/activities/{$activity->ulid}/menu")->assertNotFound();
        $this->actingAs($owner, 'sanctum')->postJson("/api/v1/organizer/activities/{$activity->ulid}/menus/{$menu->ulid}/publish")->assertOk();
        $this->actingAs($customer, 'sanctum')->getJson("/api/v1/activities/{$activity->ulid}/menu")->assertOk()
            ->assertJsonPath('data.sections.0.items.0.name', 'Carbonara')->assertJsonPath('data.sections.0.items.0.allergens', null);
        $this->assertDatabaseHas('analytics_events', ['type' => 'menu_published']);
        $this->assertDatabaseHas('analytics_events', ['type' => 'menu_viewed']);
    }

    public function test_preorder_requires_owned_valid_reservation_snapshots_price_and_cancels_with_reservation(): void
    {
        [$customer, $owner, $activity, $service, $menu, $item] = $this->domain();
        $menu->update(['status' => 'published']);
        $service->update(['preorder_enabled' => true, 'preorder_menu_id' => $menu->id, 'preorder_edit_deadline_minutes' => 60]);
        $reservation = Reservation::query()->create(['ulid' => (string) Str::ulid(), 'user_id' => $customer->id, 'activity_id' => $activity->id, 'service_id' => $service->id, 'starts_at' => now()->addDay(), 'party_size' => 2, 'status' => ReservationStatus::Confirmed, 'contact_phone' => '+393331234567', 'idempotency_key' => (string) Str::uuid()]);
        $payload = ['items' => [['menu_item_ulid' => $item->ulid, 'quantity' => 2]], 'notes' => 'Senza pepe'];
        $this->actingAs($owner, 'sanctum')->putJson("/api/v1/me/reservations/{$reservation->ulid}/preorder", $payload)->assertForbidden();
        $this->actingAs($customer, 'sanctum')->putJson("/api/v1/me/reservations/{$reservation->ulid}/preorder", $payload)->assertOk()->assertJsonPath('data.total_known_amount', 2400)->assertJsonPath('data.items.0.unit_price_snapshot', 1200);
        $item->update(['price_cents' => 1600]);
        $this->assertDatabaseHas('preorder_items', ['item_name_snapshot' => 'Carbonara', 'unit_price_snapshot' => 1200, 'subtotal' => 2400]);
        $this->actingAs($customer, 'sanctum')->postJson("/api/v1/me/reservations/{$reservation->ulid}/cancel", ['reason' => 'Cambio programma'])->assertOk();
        $this->assertDatabaseHas('preorders', ['reservation_id' => $reservation->id, 'status' => 'cancelled']);
    }

    public function test_unavailable_item_and_deadline_are_enforced_server_side(): void
    {
        [$customer, , $activity, $service, $menu, $item] = $this->domain();
        $menu->update(['status' => 'published']);
        $service->update(['preorder_enabled' => true, 'preorder_menu_id' => $menu->id, 'preorder_edit_deadline_minutes' => 120]);
        $reservation = Reservation::query()->create(['ulid' => (string) Str::ulid(), 'user_id' => $customer->id, 'activity_id' => $activity->id, 'service_id' => $service->id, 'starts_at' => now()->addHour(), 'party_size' => 1, 'status' => ReservationStatus::Confirmed, 'contact_phone' => '+393331234567', 'idempotency_key' => (string) Str::uuid()]);
        $payload = ['items' => [['menu_item_ulid' => $item->ulid, 'quantity' => 1]]];
        $this->actingAs($customer, 'sanctum')->putJson("/api/v1/me/reservations/{$reservation->ulid}/preorder", $payload)->assertUnprocessable();
        $reservation->update(['starts_at' => now()->addDay()]);
        $item->update(['available' => false]);
        $this->actingAs($customer, 'sanctum')->putJson("/api/v1/me/reservations/{$reservation->ulid}/preorder", $payload)->assertUnprocessable();
    }

    public function test_docx_import_is_draft_best_effort_and_never_infers_allergens(): void
    {
        [, , $activity] = $this->domain();
        $path = $this->archive(['word/document.xml' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>PRIMI</w:t></w:r></w:p><w:p><w:r><w:t>Carbonara .... 12 €</w:t></w:r></w:p></w:body></w:document>']);
        $result = app(MenuImportService::class)->import($activity, new UploadedFile($path, 'menu.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true));
        $this->assertSame('draft', $result['menu']->status);
        $this->assertSame(1, $result['report']['rows_imported']);
        $this->assertNull($result['menu']->sections->first()->items->first()->allergens);
    }

    public function test_xlsx_common_headers_missing_price_and_malformed_archives_are_reported(): void
    {
        [, , $activity] = $this->domain();
        $shared = '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Categoria</t></si><si><t>Piatto</t></si><si><t>Prezzo</t></si><si><t>Antipasti</t></si><si><t>Bruschetta</t></si></sst>';
        $sheet = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row><c t="s"><v>0</v></c><c t="s"><v>1</v></c><c t="s"><v>2</v></c></row><row><c t="s"><v>3</v></c><c t="s"><v>4</v></c><c><v></v></c></row></sheetData></worksheet>';
        $path = $this->archive(['xl/sharedStrings.xml' => $shared, 'xl/worksheets/sheet1.xml' => $sheet]);
        $result = app(MenuImportService::class)->import($activity, new UploadedFile($path, 'menu.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true));
        $this->assertSame(1, $result['report']['rows_imported']);
        $this->assertNotEmpty($result['report']['warnings']);
        $invalid = tempnam(sys_get_temp_dir(), 'bad-menu');
        file_put_contents($invalid, 'not-a-zip');
        $this->expectException(ValidationException::class);
        app(MenuImportService::class)->import($activity, new UploadedFile($invalid, 'bad.xlsx', null, null, true));
    }

    private function archive(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'menu-archive');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $path;
    }

    private function domain(): array
    {
        $customer = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active']);
        $owner = User::factory()->create(['role' => UserRole::Organizer, 'status' => 'active']);
        $region = Region::query()->create(['name' => 'Molise', 'code' => 'MOL']);
        $province = Province::query()->create(['region_id' => $region->id, 'name' => 'Campobasso', 'code' => 'CB']);
        $municipality = Municipality::query()->create(['province_id' => $province->id, 'name' => 'Campobasso', 'istat_code' => '070006']);
        $organizer = OrganizerProfile::query()->create(['ulid' => (string) Str::ulid(), 'owner_user_id' => $owner->id, 'name' => 'Locale', 'type' => 'restaurant', 'email' => 'menu@example.test', 'municipality_id' => $municipality->id, 'verification_status' => 'verified']);
        $activity = Activity::query()->create(['ulid' => (string) Str::ulid(), 'organizer_profile_id' => $organizer->id, 'municipality_id' => $municipality->id, 'name' => 'Ristorante', 'slug' => 'ristorante-menu', 'type' => ActivityType::Restaurant, 'status' => ActivityStatus::Published, 'booking_enabled' => true]);
        $service = ActivityService::query()->create(['ulid' => (string) Str::ulid(), 'activity_id' => $activity->id, 'name' => 'Cena', 'type' => 'dinner', 'active' => true, 'booking_enabled' => true, 'min_party_size' => 1, 'max_party_size' => 8, 'minimum_advance_minutes' => 0, 'indicative_duration_minutes' => 90, 'capacity_mode' => 'covers']);
        $menu = Menu::query()->create(['ulid' => (string) Str::ulid(), 'activity_id' => $activity->id, 'name' => 'Cena', 'status' => 'draft', 'active' => true]);
        $section = $menu->sections()->create(['ulid' => (string) Str::ulid(), 'name' => 'Primi', 'sort_order' => 0]);
        $item = MenuItem::query()->create(['ulid' => (string) Str::ulid(), 'menu_section_id' => $section->id, 'name' => 'Carbonara', 'price_cents' => 1200, 'available' => true, 'active' => true, 'allergen_information_verified' => false]);

        return [$customer, $owner, $activity, $service, $menu, $item];
    }
}
