<?php

namespace Tests\Feature;

use App\Actions\Events\SyncEventTicketTypes;
use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\OrganizerProfile;
use App\Models\Province;
use App\Models\Region;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class OrganizerEventManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_organizer_creates_an_owned_draft_with_tickets(): void
    {
        [$organizer, $profile, $municipality, $category] = $this->domain();

        $response = $this->actingAs($organizer, 'sanctum')
            ->postJson('/api/v1/organizer/events', $this->payload($municipality, $category))
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.ticket_types.0.name', 'Ingresso');

        $event = Event::query()->where('ulid', $response->json('data.ulid'))->firstOrFail();
        $this->assertSame($profile->id, $event->organizer_profile_id);
        $this->assertNull($event->published_at);
        $this->assertDatabaseHas('ticket_types', [
            'event_id' => $event->id,
            'booked_quantity' => 0,
            'max_quantity' => 30,
            'price_cents' => 1500,
        ]);
    }

    public function test_create_requires_approved_organizer_and_rejects_protected_fields(): void
    {
        [$organizer, $profile, $municipality, $category] = $this->domain();
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/organizer/events', $this->payload($municipality, $category))
            ->assertForbidden();

        $profile->forceFill(['verification_status' => 'pending', 'verified_at' => null])->save();
        $this->actingAs($organizer, 'sanctum')
            ->postJson('/api/v1/organizer/events', $this->payload($municipality, $category))
            ->assertForbidden();

        $profile->forceFill(['verification_status' => 'verified', 'verified_at' => now()])->save();
        $organizer->unsetRelation('organizerProfile');
        $payload = [
            ...$this->payload($municipality, $category),
            'organizer_profile_id' => 999,
            'status' => 'published',
            'available_total' => 999,
            'ticket_types' => [[
                ...$this->payload($municipality, $category)['ticket_types'][0],
                'booked_quantity' => 99,
            ]],
        ];

        $this->actingAs($organizer, 'sanctum')
            ->postJson('/api/v1/organizer/events', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['organizer_profile_id', 'status', 'available_total', 'ticket_types.0.booked_quantity']);
    }

    public function test_invalid_dates_prices_and_quantities_return_validation_errors(): void
    {
        [$organizer, , $municipality, $category] = $this->domain();
        $payload = $this->payload($municipality, $category);
        $payload['ends_at'] = now()->addDays(2)->toIso8601String();
        $payload['starts_at'] = now()->addDays(3)->toIso8601String();
        $payload['ticket_types'][0]['price_cents'] = -1;
        $payload['ticket_types'][0]['max_quantity'] = 0;

        $this->actingAs($organizer, 'sanctum')
            ->postJson('/api/v1/organizer/events', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ends_at', 'ticket_types.0.price_cents', 'ticket_types.0.max_quantity']);
    }

    public function test_ticket_failure_rolls_back_event_creation(): void
    {
        [$organizer, , $municipality, $category] = $this->domain();
        $this->mock(SyncEventTicketTypes::class, function ($mock): void {
            $mock->shouldReceive('handle')->once()->andThrow(new RuntimeException('sync failed'));
        });
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($organizer, 'sanctum')
                ->postJson('/api/v1/organizer/events', $this->payload($municipality, $category));
            $this->fail('La sincronizzazione ticket doveva fallire.');
        } catch (RuntimeException $exception) {
            $this->assertSame('sync failed', $exception->getMessage());
        }

        $this->assertDatabaseCount('events', 0);
        $this->assertDatabaseCount('ticket_types', 0);
    }

    public function test_complete_draft_is_published_but_incomplete_or_invalid_state_is_rejected(): void
    {
        [$organizer, $profile, $municipality, $category] = $this->domain();
        $complete = $this->event($profile, $municipality, $category, EventStatus::Draft);
        TicketType::query()->create([
            'event_id' => $complete->id,
            'name' => 'Ingresso',
            'audience' => 'generic',
            'max_quantity' => 20,
            'price_cents' => 1000,
            'is_active' => true,
        ]);

        $this->actingAs($organizer, 'sanctum')
            ->postJson("/api/v1/organizer/events/{$complete->ulid}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
        $this->assertNotNull($complete->refresh()->published_at);

        $incomplete = $this->event($profile, $municipality, $category, EventStatus::Draft, 'Bozza incompleta');
        $this->actingAs($organizer, 'sanctum')
            ->postJson("/api/v1/organizer/events/{$incomplete->ulid}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ticket_types');

        $completed = $this->event($profile, $municipality, $category, EventStatus::Completed, 'Evento concluso');
        $this->actingAs($organizer, 'sanctum')
            ->postJson("/api/v1/organizer/events/{$completed->ulid}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_organizer_cannot_view_update_or_publish_another_organizers_event(): void
    {
        [$organizer] = $this->domain();
        [, $otherProfile, $municipality, $category] = $this->domain();
        $event = $this->event($otherProfile, $municipality, $category, EventStatus::Draft);

        $this->actingAs($organizer, 'sanctum')->getJson("/api/v1/organizer/events/{$event->ulid}")->assertForbidden();
        $this->actingAs($organizer, 'sanctum')->putJson(
            "/api/v1/organizer/events/{$event->ulid}",
            $this->payload($municipality, $category),
        )->assertForbidden();
        $this->actingAs($organizer, 'sanctum')->postJson("/api/v1/organizer/events/{$event->ulid}/publish")->assertForbidden();
    }

    public function test_update_preserves_ticket_identity_and_booked_quantity_and_adds_new_ticket(): void
    {
        [$organizer, $profile, $municipality, $category] = $this->domain();
        $event = $this->event($profile, $municipality, $category, EventStatus::Draft);
        $ticket = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Originale',
            'audience' => 'generic',
            'max_quantity' => 20,
            'booked_quantity' => 4,
            'price_cents' => 1000,
            'is_active' => true,
        ]);
        $event->forceFill(['capacity_total' => 50, 'available_total' => 46])->save();
        $this->booking($event, 4);
        $payload = $this->payload($municipality, $category);
        $payload['title'] = 'Titolo modificato';
        $payload['capacity_total'] = 40;
        $payload['ticket_types'] = [
            ['ulid' => $ticket->ulid, 'name' => 'Aggiornato', 'audience' => 'generic', 'price_cents' => 1200, 'max_quantity' => 40, 'is_active' => true],
            ['name' => 'Ridotto', 'audience' => 'generic', 'price_cents' => 700, 'max_quantity' => 5, 'is_active' => true],
        ];

        $this->actingAs($organizer, 'sanctum')
            ->putJson("/api/v1/organizer/events/{$event->ulid}", $payload)
            ->assertOk()
            ->assertJsonPath('data.title', 'Titolo modificato')
            ->assertJsonPath('data.capacity_total', 40)
            ->assertJsonPath('data.available_total', 36)
            ->assertJsonPath('data.summary_metrics.capacity_total', 40)
            ->assertJsonPath('data.summary_metrics.available_total', 36);

        $ticket->refresh();
        $this->assertSame('Aggiornato', $ticket->name);
        $this->assertSame(4, $ticket->booked_quantity);
        $this->assertSame(40, $ticket->max_quantity);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'capacity_total' => 40, 'available_total' => 36]);
        $this->assertDatabaseCount('ticket_types', 2);
    }

    public function test_capacity_uses_only_valid_bookings_and_rejects_values_below_them(): void
    {
        [$organizer, $profile, $municipality, $category] = $this->domain();
        $event = $this->event($profile, $municipality, $category, EventStatus::Draft);
        $event->forceFill(['capacity_total' => 50, 'available_total' => 32])->save();
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        foreach ([['confirmed', 18], ['cancelled_by_customer', 5]] as [$status, $people]) {
            Booking::query()->create([
                'ulid' => (string) Str::ulid(),
                'user_id' => $customer->id,
                'event_id' => $event->id,
                'status' => $status,
                'total_people' => $people,
                'adults_count' => $people,
                'children_count' => 0,
                'booking_code' => Str::upper(Str::random(12)),
                'qr_token_hash' => hash('sha256', Str::random(32)),
                'booked_at' => now(),
            ]);
        }

        $this->actingAs($organizer, 'sanctum')
            ->getJson("/api/v1/organizer/events/{$event->ulid}")
            ->assertOk()
            ->assertJsonPath('data.capacity_total', 50)
            ->assertJsonPath('data.summary_metrics.booked_people', 18)
            ->assertJsonPath('data.summary_metrics.available_total', 32);
        $payload = $this->payload($municipality, $category);
        $payload['capacity_total'] = 29;
        $this->actingAs($organizer, 'sanctum')
            ->putJson("/api/v1/organizer/events/{$event->ulid}", $payload)
            ->assertOk()
            ->assertJsonPath('data.capacity_total', 29)
            ->assertJsonPath('data.available_total', 11)
            ->assertJsonPath('data.summary_metrics.booked_people', 18)
            ->assertJsonPath('data.summary_metrics.available_total', 11);

        $payload['capacity_total'] = 18;
        $this->actingAs($organizer, 'sanctum')
            ->putJson("/api/v1/organizer/events/{$event->ulid}", $payload)
            ->assertOk()
            ->assertJsonPath('data.available_total', 0);

        $payload['capacity_total'] = 17;
        $this->actingAs($organizer, 'sanctum')
            ->putJson("/api/v1/organizer/events/{$event->ulid}", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('capacity_total');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'capacity_total' => 18,
            'available_total' => 0,
        ]);
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_update_rejects_capacity_below_bookings_and_removal_or_reduction_of_booked_ticket(): void
    {
        [$organizer, $profile, $municipality, $category] = $this->domain();
        $event = $this->event($profile, $municipality, $category, EventStatus::Draft);
        $ticket = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Prenotato',
            'audience' => 'generic',
            'max_quantity' => 20,
            'booked_quantity' => 6,
            'price_cents' => 1000,
            'is_active' => true,
        ]);
        $event->forceFill(['capacity_total' => 20, 'available_total' => 14])->save();
        $this->booking($event, 6);

        $capacityPayload = $this->payload($municipality, $category);
        $capacityPayload['capacity_total'] = 5;
        $capacityPayload['ticket_types'][0]['max_quantity'] = 20;
        $this->actingAs($organizer, 'sanctum')
            ->putJson("/api/v1/organizer/events/{$event->ulid}", $capacityPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('capacity_total');

        $reductionPayload = $this->payload($municipality, $category);
        $reductionPayload['ticket_types'] = [[
            'ulid' => $ticket->ulid,
            'name' => 'Prenotato',
            'audience' => 'generic',
            'price_cents' => 1000,
            'max_quantity' => 5,
        ]];
        $this->actingAs($organizer, 'sanctum')
            ->putJson("/api/v1/organizer/events/{$event->ulid}", $reductionPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ticket_types.0.max_quantity');

        $removalPayload = $this->payload($municipality, $category);
        $this->actingAs($organizer, 'sanctum')
            ->putJson("/api/v1/organizer/events/{$event->ulid}", $removalPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ticket_types');

        $this->assertDatabaseHas('ticket_types', ['id' => $ticket->id, 'booked_quantity' => 6]);
    }

    public function test_unbooked_ticket_can_be_removed_and_sync_failure_rolls_back_event_update(): void
    {
        [$organizer, $profile, $municipality, $category] = $this->domain();
        $event = $this->event($profile, $municipality, $category, EventStatus::Draft);
        $removable = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Da rimuovere',
            'audience' => 'generic',
            'max_quantity' => 20,
            'price_cents' => 1000,
            'is_active' => true,
        ]);

        $this->actingAs($organizer, 'sanctum')
            ->putJson("/api/v1/organizer/events/{$event->ulid}", $this->payload($municipality, $category))
            ->assertOk();
        $this->assertDatabaseMissing('ticket_types', ['id' => $removable->id]);

        $this->mock(SyncEventTicketTypes::class, function ($mock): void {
            $mock->shouldReceive('handle')->once()->andThrow(new RuntimeException('sync failed'));
        });
        $this->withoutExceptionHandling();
        $payload = $this->payload($municipality, $category);
        $payload['title'] = 'Titolo da annullare';

        try {
            $this->actingAs($organizer, 'sanctum')->putJson("/api/v1/organizer/events/{$event->ulid}", $payload);
            $this->fail('La sincronizzazione ticket doveva fallire.');
        } catch (RuntimeException $exception) {
            $this->assertSame('sync failed', $exception->getMessage());
        }

        $this->assertNotSame('Titolo da annullare', $event->refresh()->title);
    }

    public function test_competition_calendar_marks_yellow_red_and_excludes_current_event(): void
    {
        [$organizer, $profile, $municipality, $category] = $this->domain();
        $otherCategory = Category::query()->create(['name' => 'Teatro', 'slug' => 'teatro', 'is_active' => true]);
        $yellowDate = now()->addMonth()->startOfMonth()->addDays(5)->setTime(18, 0);
        $redDate = $yellowDate->copy()->addDay();

        $yellow = $this->event($profile, $municipality, $otherCategory, EventStatus::Published, 'Concorrenza generica');
        $yellow->forceFill(['starts_at' => $yellowDate, 'ends_at' => $yellowDate->addHours(2)])->save();
        foreach (['Concorrenza categoria uno', 'Concorrenza categoria due'] as $title) {
            $event = $this->event($profile, $municipality, $category, EventStatus::Published, $title);
            $event->forceFill(['starts_at' => $redDate, 'ends_at' => $redDate->addHours(2)])->save();
        }

        $response = $this->actingAs($organizer, 'sanctum')->getJson('/api/v1/organizer/calendar/availability?'.http_build_query([
            'month' => $yellowDate->month, 'year' => $yellowDate->year,
            'municipality_id' => $municipality->id, 'category_id' => $category->id,
            'exclude_event_ulid' => $yellow->ulid,
        ]))->assertOk();

        $days = collect($response->json('data'))->keyBy('date');
        $this->assertSame('none', $days[$yellowDate->toDateString()]['color']);
        $this->assertSame('red', $days[$redDate->toDateString()]['color']);
        $this->assertSame(2, $days[$redDate->toDateString()]['same_category_count']);

        $withoutExclusion = $this->actingAs($organizer, 'sanctum')->getJson('/api/v1/organizer/calendar/availability?'.http_build_query([
            'month' => $yellowDate->month, 'year' => $yellowDate->year,
            'municipality_id' => $municipality->id, 'category_id' => $category->id,
        ]))->assertOk();
        $allDays = collect($withoutExclusion->json('data'))->keyBy('date');
        $this->assertSame('yellow', $allDays[$yellowDate->toDateString()]['color']);
    }

    /** @return array{User, OrganizerProfile, Municipality, Category} */
    private function domain(): array
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer, 'status' => 'active']);
        $region = Region::query()->create(['name' => fake()->unique()->state(), 'code' => fake()->unique()->lexify('???')]);
        $province = Province::query()->create(['region_id' => $region->id, 'name' => fake()->city(), 'code' => fake()->unique()->lexify('??')]);
        $municipality = Municipality::query()->create(['province_id' => $province->id, 'name' => fake()->city(), 'istat_code' => fake()->unique()->numerify('######')]);
        $category = Category::query()->create(['name' => fake()->unique()->word(), 'slug' => fake()->unique()->slug(), 'is_active' => true]);
        $profile = OrganizerProfile::query()->create([
            'ulid' => (string) Str::ulid(),
            'owner_user_id' => $organizer->id,
            'name' => fake()->company(),
            'type' => 'associazione',
            'email' => fake()->unique()->safeEmail(),
            'municipality_id' => $municipality->id,
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);

        return [$organizer, $profile, $municipality, $category];
    }

    /** @return array<string, mixed> */
    private function payload(Municipality $municipality, Category $category): array
    {
        return [
            'title' => 'Degustazione in cantina',
            'summary' => 'Una degustazione guidata di vini locali.',
            'description' => 'Esperienza completa con visita e degustazione.',
            'municipality_id' => $municipality->id,
            'category_ids' => [$category->id],
            'starts_at' => now()->addMonth()->toIso8601String(),
            'ends_at' => now()->addMonth()->addHours(2)->toIso8601String(),
            'capacity_total' => 30,
            'is_free' => false,
            'family_friendly' => false,
            'indoor_outdoor' => 'indoor',
            'ticket_types' => [[
                'name' => 'Ingresso',
                'audience' => 'generic',
                'price_cents' => 1500,
                'max_quantity' => 30,
                'is_active' => true,
            ]],
        ];
    }

    private function booking(Event $event, int $people, string $status = 'confirmed'): Booking
    {
        $customer = User::factory()->create(['role' => UserRole::Customer]);

        return Booking::query()->create([
            'ulid' => (string) Str::ulid(),
            'user_id' => $customer->id,
            'event_id' => $event->id,
            'status' => $status,
            'total_people' => $people,
            'adults_count' => $people,
            'children_count' => 0,
            'booking_code' => Str::upper(Str::random(12)),
            'qr_token_hash' => hash('sha256', Str::random(32)),
            'booked_at' => now(),
        ]);
    }

    private function event(OrganizerProfile $profile, Municipality $municipality, Category $category, EventStatus $status, string $title = 'Evento modificabile'): Event
    {
        $event = Event::query()->create([
            'ulid' => (string) Str::ulid(),
            'organizer_profile_id' => $profile->id,
            'municipality_id' => $municipality->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('####'),
            'summary' => 'Riepilogo evento completo.',
            'description' => 'Descrizione evento completa.',
            'status' => $status,
            'starts_at' => now()->addMonth(),
            'ends_at' => now()->addMonth()->addHours(2),
            'capacity_total' => 20,
            'available_total' => 20,
            'is_free' => false,
            'family_friendly' => false,
            'indoor_outdoor' => 'indoor',
        ]);
        $event->categories()->sync([$category->id]);

        return $event;
    }
}
