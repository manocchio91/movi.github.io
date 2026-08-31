<?php

namespace Tests\Feature;

use App\Actions\Waitlist\ExpireWaitlistOffers;
use App\Actions\Waitlist\PromoteEventWaitlist;
use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\AnalyticsEvent;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\OrganizerProfile;
use App\Models\Province;
use App\Models\Region;
use App\Models\TicketType;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AutomaticWaitlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_join_is_idempotent_exposes_fifo_position_and_leave_is_idempotent(): void
    {
        [$event, $ticket, $first, $second] = $this->domain();
        $this->getJson("/api/v1/events/{$event->ulid}")->assertOk()->assertJsonPath('data.status', 'sold_out');

        $this->actingAs($first, 'sanctum')->postJson("/api/v1/events/{$event->ulid}/waitlist", $this->payload($ticket))
            ->assertCreated()->assertJsonPath('data.position', 1);
        $this->postJson("/api/v1/events/{$event->ulid}/waitlist", $this->payload($ticket))
            ->assertCreated()->assertJsonPath('data.position', 1);
        $this->actingAs($second, 'sanctum')->postJson("/api/v1/events/{$event->ulid}/waitlist", $this->payload($ticket))
            ->assertCreated()->assertJsonPath('data.position', 2);
        $this->assertDatabaseCount('waitlist_entries', 2);
        $this->assertSame(2, AnalyticsEvent::query()->where('type', 'waitlist_joined')->count());

        $this->actingAs($first, 'sanctum')->deleteJson("/api/v1/events/{$event->ulid}/waitlist")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->deleteJson("/api/v1/events/{$event->ulid}/waitlist")->assertOk();
    }

    public function test_cancellation_promotes_one_compatible_candidate_and_reserves_capacity(): void
    {
        [$event, $ticket, $first, $second, $bookedCustomer, $booking] = $this->domain(withBooking: true);
        $this->join($first, $event, $ticket);
        $this->join($second, $event, $ticket);

        $this->actingAs($bookedCustomer, 'sanctum')->postJson("/api/v1/me/bookings/{$booking->ulid}/cancel")->assertOk();

        $this->assertDatabaseHas('waitlist_entries', ['user_id' => $first->id, 'status' => 'offered']);
        $this->assertDatabaseHas('waitlist_entries', ['user_id' => $second->id, 'status' => 'waiting']);
        $this->assertSame(0, $event->refresh()->available_total);
        $this->assertDatabaseHas('notifications', ['user_id' => $first->id, 'kind' => 'waitlist_offer']);
    }

    public function test_accept_converts_offer_to_one_booking_idempotently_without_overbooking(): void
    {
        [$event, $ticket, $first] = $this->domain();
        $this->join($first, $event, $ticket, bypassAvailability: true);
        $event->forceFill(['available_total' => 1, 'status' => EventStatus::Published])->save();
        app(PromoteEventWaitlist::class)->handle($event->id);

        $firstResponse = $this->actingAs($first, 'sanctum')->postJson("/api/v1/events/{$event->ulid}/waitlist/accept", ['phone' => '+393331234567'])
            ->assertCreated();
        $bookingUlid = $firstResponse->json('data.ulid');
        $this->postJson("/api/v1/events/{$event->ulid}/waitlist/accept", ['phone' => '+393331234567'])
            ->assertCreated()->assertJsonPath('data.ulid', $bookingUlid);

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('waitlist_entries', ['user_id' => $first->id, 'status' => 'accepted']);
        $this->assertDatabaseHas('analytics_events', ['type' => 'waitlist_converted', 'user_id' => $first->id]);
        $this->assertSame(0, $event->refresh()->available_total);
    }

    public function test_expired_offer_releases_capacity_and_promotes_next_candidate(): void
    {
        [$event, $ticket, $first, $second] = $this->domain();
        $this->join($first, $event, $ticket, bypassAvailability: true);
        $this->join($second, $event, $ticket, bypassAvailability: true);
        $event->forceFill(['available_total' => 1, 'status' => EventStatus::Published])->save();
        app(PromoteEventWaitlist::class)->handle($event->id);
        WaitlistEntry::query()->where('user_id', $first->id)->update(['reserved_until' => now()->subSecond()]);

        $this->assertSame(1, app(ExpireWaitlistOffers::class)->handle());
        $this->assertDatabaseHas('waitlist_entries', ['user_id' => $first->id, 'status' => 'expired']);
        $this->assertDatabaseHas('waitlist_entries', ['user_id' => $second->id, 'status' => 'offered']);
        $this->assertSame(0, $event->refresh()->available_total);
    }

    public function test_only_one_offer_is_created_when_promotion_is_retried(): void
    {
        [$event, $ticket, $first, $second] = $this->domain();
        $this->join($first, $event, $ticket, bypassAvailability: true);
        $this->join($second, $event, $ticket, bypassAvailability: true);
        $event->forceFill(['available_total' => 1, 'status' => EventStatus::Published])->save();

        app(PromoteEventWaitlist::class)->handle($event->id);
        app(PromoteEventWaitlist::class)->handle($event->id);

        $this->assertSame(1, WaitlistEntry::query()->where('status', 'offered')->count());
        $this->assertSame(0, $event->refresh()->available_total);
    }

    private function join(User $user, Event $event, TicketType $ticket, bool $bypassAvailability = false): void
    {
        if ($bypassAvailability) {
            $event->forceFill(['available_total' => 0, 'status' => EventStatus::SoldOut])->save();
        }
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/events/{$event->ulid}/waitlist", $this->payload($ticket))->assertCreated();
    }

    private function payload(TicketType $ticket): array
    {
        return ['adults_count' => 1, 'children_count' => 0, 'items' => [['ticket_type_ulid' => $ticket->ulid, 'quantity' => 1]]];
    }

    private function domain(bool $withBooking = false): array
    {
        $first = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active']);
        $second = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active']);
        $bookedCustomer = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active']);
        $organizer = User::factory()->create(['role' => UserRole::Organizer, 'status' => 'active']);
        $region = Region::query()->create(['name' => 'Molise '.Str::random(4), 'code' => Str::upper(Str::random(3))]);
        $province = Province::query()->create(['region_id' => $region->id, 'name' => 'Campobasso', 'code' => Str::upper(Str::random(2))]);
        $municipality = Municipality::query()->create(['province_id' => $province->id, 'name' => 'Campobasso', 'istat_code' => Str::random(6)]);
        $profile = OrganizerProfile::query()->create(['ulid' => (string) Str::ulid(), 'owner_user_id' => $organizer->id, 'name' => 'Cantina', 'type' => 'winery', 'email' => 'cantina@example.test', 'municipality_id' => $municipality->id, 'verification_status' => 'verified']);
        $event = Event::query()->create(['ulid' => (string) Str::ulid(), 'organizer_profile_id' => $profile->id, 'municipality_id' => $municipality->id, 'title' => 'Evento pieno', 'slug' => 'evento-'.Str::lower(Str::random(6)), 'summary' => 'Test', 'description' => 'Test', 'status' => EventStatus::SoldOut, 'starts_at' => now()->addWeek(), 'capacity_total' => 1, 'available_total' => 0, 'is_free' => true, 'family_friendly' => false, 'indoor_outdoor' => 'indoor']);
        $ticket = TicketType::query()->create(['ulid' => (string) Str::ulid(), 'event_id' => $event->id, 'name' => 'Ingresso', 'audience' => 'adult', 'max_quantity' => 1, 'booked_quantity' => $withBooking ? 1 : 0, 'price_cents' => 0, 'is_active' => true]);
        $booking = null;
        if ($withBooking) {
            $booking = Booking::query()->create(['ulid' => (string) Str::ulid(), 'user_id' => $bookedCustomer->id, 'event_id' => $event->id, 'status' => 'confirmed', 'total_people' => 1, 'adults_count' => 1, 'booking_code' => 'WL-'.Str::upper(Str::random(8)), 'contact_phone' => '+393331234567', 'qr_token_hash' => Str::random(60), 'qr_token_encrypted' => Str::random(64), 'booked_at' => now(), 'event_terms_accepted' => true]);
            $booking->items()->create(['ticket_type_id' => $ticket->id, 'quantity' => 1]);
        }

        return [$event, $ticket, $first, $second, $bookedCustomer, $booking];
    }
}
