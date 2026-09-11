<?php

namespace Tests\Feature;

use App\Actions\Reservations\SendReservationReminders;
use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\ActivityService;
use App\Models\AnalyticsEvent;
use App\Models\Municipality;
use App\Models\OrganizerProfile;
use App\Models\PreOrder;
use App\Models\Province;
use App\Models\Region;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActivityReservationEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_manager_filters_authorization_attendance_and_metrics(): void
    {
        [$organizer, $activity, $service] = $this->bookableDomain();
        [$other] = $this->organizerDomain('ManagerOther');
        $customer = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active', 'name' => 'Cliente Manager']);
        $reservation = Reservation::query()->create(['ulid' => (string) Str::ulid(), 'user_id' => $customer->id, 'activity_id' => $activity->id, 'service_id' => $service->id, 'starts_at' => now()->subHours(3), 'party_size' => 3, 'status' => ReservationStatus::Confirmed, 'contact_phone' => '+393331234567', 'idempotency_key' => 'manager-transition']);

        $this->actingAs($other, 'sanctum')->getJson('/api/v1/organizer/reservations')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson("/api/v1/organizer/reservations/{$reservation->ulid}/status", ['status' => 'completed'])->assertForbidden();
        $this->actingAs($organizer, 'sanctum')->getJson('/api/v1/organizer/reservations?status=confirmed&search=Cliente')->assertOk()->assertJsonPath('data.0.ulid', $reservation->ulid);
        $this->patchJson("/api/v1/organizer/reservations/{$reservation->ulid}/status", ['status' => 'completed'])->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertDatabaseHas('notifications', ['reservation_id' => $reservation->id, 'kind' => 'reservation_completed']);
        $this->getJson('/api/v1/organizer/reservations/customers')->assertOk()->assertJsonPath('data.0.name', 'Cliente Manager');
        $this->getJson('/api/v1/organizer/reservations/analytics')->assertOk()->assertJsonPath('data.completed', 1)->assertJsonPath('data.covers', 3);
    }

    public function test_reservation_reminder_is_centrally_timed_and_deduplicated(): void
    {
        [, $activity, $service] = $this->bookableDomain();
        $customer = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active']);
        $reservation = Reservation::query()->create(['ulid' => (string) Str::ulid(), 'user_id' => $customer->id, 'activity_id' => $activity->id, 'service_id' => $service->id, 'starts_at' => now()->addHours(24), 'party_size' => 2, 'status' => ReservationStatus::Confirmed, 'contact_phone' => '+393331234567', 'idempotency_key' => 'reminder']);
        PreOrder::query()->create(['ulid' => (string) Str::ulid(), 'reservation_id' => $reservation->id, 'status' => 'confirmed', 'total_known_amount' => 1200]);
        $sender = app(SendReservationReminders::class);
        $this->assertSame(1, $sender->handle(24));
        $this->assertSame(0, $sender->handle(24));
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', ['reservation_id' => $reservation->id, 'kind' => 'reservation_reminder', 'payload->has_preorder' => true]);
    }

    public function test_organizer_owns_activity_crud_service_and_capacity_configuration(): void
    {
        [$organizer, , $municipality] = $this->organizerDomain('Owner');
        [$other] = $this->organizerDomain('Other');

        $activity = $this->actingAs($organizer, 'sanctum')->postJson('/api/v1/organizer/activities', $this->activityPayload($municipality))
            ->assertCreated()->assertJsonPath('data.type', 'restaurant');
        $ulid = $activity->json('data.ulid');
        $this->actingAs($other, 'sanctum')->putJson("/api/v1/organizer/activities/{$ulid}", $this->activityPayload($municipality))->assertForbidden();

        $service = $this->actingAs($organizer, 'sanctum')->postJson("/api/v1/organizer/activities/{$ulid}/services", $this->servicePayload())
            ->assertCreated()->assertJsonPath('data.capacity_mode', 'covers');
        $serviceUlid = $service->json('data.ulid');
        $this->putJson("/api/v1/organizer/activities/{$ulid}/services/{$serviceUlid}/availability", [
            'rules' => [['weekday' => 6, 'start_time' => '12:30', 'capacity' => 10, 'effective_from' => '2030-06-01', 'effective_to' => '2030-06-30']],
            'exceptions' => [['date' => '2030-06-01', 'is_closed' => true, 'note' => 'Chiusura']],
        ])->assertOk()->assertJsonPath('data.rules.0.capacity', 10)->assertJsonPath('data.rules.0.effective_from', '2030-06-01T00:00:00.000000Z')->assertJsonPath('data.exceptions.0.is_closed', true);
        $this->getJson("/api/v1/activities/{$ulid}/services/{$serviceUlid}/availability?date=2030-06-08")->assertOk()->assertJsonPath('data.0.capacity', 10);
        $this->getJson("/api/v1/activities/{$ulid}/services/{$serviceUlid}/availability?date=2030-07-06")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_real_availability_reservation_idempotency_exhaustion_and_cancellation(): void
    {
        [, $activity, $service] = $this->bookableDomain(capacity: 4);
        $first = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active']);
        $second = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active']);
        $startsAt = now()->next('Saturday')->setTime(12, 30)->seconds(0);

        $this->getJson("/api/v1/activities/{$activity->ulid}/services/{$service->ulid}/availability?date={$startsAt->toDateString()}")
            ->assertOk()->assertJsonPath('data.0.available', 4);
        $payload = ['service_ulid' => $service->ulid, 'starts_at' => $startsAt->toIso8601String(), 'party_size' => 3, 'contact_phone' => '+393331234567'];
        $reservation = $this->actingAs($first, 'sanctum')->withHeader('Idempotency-Key', 'reservation-one')->postJson("/api/v1/activities/{$activity->ulid}/reservations", $payload)->assertCreated();
        $reservationUlid = $reservation->json('data.ulid');
        $this->withHeader('Idempotency-Key', 'reservation-one')->postJson("/api/v1/activities/{$activity->ulid}/reservations", $payload)->assertCreated()->assertJsonPath('data.ulid', $reservationUlid);
        $this->actingAs($second, 'sanctum')->withHeader('Idempotency-Key', 'competing-reservation')->postJson("/api/v1/activities/{$activity->ulid}/reservations", [...$payload, 'party_size' => 2])->assertConflict();
        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseHas('notifications', ['kind' => 'reservation_confirmed', 'user_id' => $first->id]);
        $this->assertDatabaseHas('analytics_events', ['type' => 'reservation_created', 'activity_id' => $activity->id]);

        $this->actingAs($first, 'sanctum')->postJson("/api/v1/me/reservations/{$reservationUlid}/cancel", ['reason' => 'Cambio programma'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->actingAs($second, 'sanctum')->withHeader('Idempotency-Key', 'after-cancel')->postJson("/api/v1/activities/{$activity->ulid}/reservations", [...$payload, 'party_size' => 4])->assertCreated();
        $this->assertDatabaseHas('notifications', ['kind' => 'reservation_cancelled', 'user_id' => $first->id]);
        $this->assertDatabaseHas('analytics_events', ['type' => 'reservation_cancelled', 'activity_id' => $activity->id]);
    }

    public function test_rules_party_advance_closure_and_future_capacity_changes_preserve_reservations(): void
    {
        [$organizer, $activity, $service] = $this->bookableDomain(capacity: 5, minimumAdvance: 120);
        $customer = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active']);
        $startsAt = now()->next('Saturday')->setTime(12, 30)->seconds(0);
        $base = ['service_ulid' => $service->ulid, 'starts_at' => $startsAt->toIso8601String(), 'contact_phone' => '+393331234567'];
        $this->actingAs($customer, 'sanctum')->withHeader('Idempotency-Key', 'party-invalid')->postJson("/api/v1/activities/{$activity->ulid}/reservations", [...$base, 'party_size' => 9])->assertUnprocessable();

        $near = now()->addHour()->seconds(0);
        $service->availabilityRules()->create(['weekday' => $near->dayOfWeekIso, 'start_time' => $near->format('H:i'), 'capacity' => 5, 'active' => true]);
        $this->withHeader('Idempotency-Key', 'advance-invalid')->postJson("/api/v1/activities/{$activity->ulid}/reservations", [...$base, 'starts_at' => $near->toIso8601String(), 'party_size' => 2])->assertUnprocessable();

        $service->forceFill(['minimum_advance_minutes' => 0])->save();
        $reservation = $this->withHeader('Idempotency-Key', 'preserved')->postJson("/api/v1/activities/{$activity->ulid}/reservations", [...$base, 'party_size' => 4])->assertCreated();
        $this->actingAs($organizer, 'sanctum')->putJson("/api/v1/organizer/activities/{$activity->ulid}/services/{$service->ulid}/availability", ['rules' => [['weekday' => 6, 'start_time' => '12:30', 'capacity' => 2]]])->assertOk();
        $this->assertDatabaseHas('reservations', ['ulid' => $reservation->json('data.ulid'), 'status' => 'confirmed', 'party_size' => 4]);
        $this->getJson("/api/v1/activities/{$activity->ulid}/services/{$service->ulid}/availability?date={$startsAt->toDateString()}")->assertOk()->assertJsonPath('data.0.available', 0);

        $closedDate = $startsAt->addWeek();
        $this->actingAs($organizer, 'sanctum')->putJson("/api/v1/organizer/activities/{$activity->ulid}/services/{$service->ulid}/availability", ['exceptions' => [['date' => $closedDate->toDateString(), 'is_closed' => true]]])->assertOk();
        $this->actingAs($customer, 'sanctum')->withHeader('Idempotency-Key', 'closed')->postJson("/api/v1/activities/{$activity->ulid}/reservations", [...$base, 'starts_at' => $closedDate->toIso8601String(), 'party_size' => 1])->assertUnprocessable();
    }

    public function test_follow_review_authorization_and_activity_analytics_are_separate(): void
    {
        [, $activity, $service] = $this->bookableDomain();
        $customer = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active']);
        $other = User::factory()->create(['role' => UserRole::Customer, 'status' => 'active']);
        $this->actingAs($customer, 'sanctum')->postJson("/api/v1/customer/activities/{$activity->ulid}/follow")->assertOk()->assertJsonPath('data.created', true);
        $this->postJson("/api/v1/customer/activities/{$activity->ulid}/follow")->assertOk()->assertJsonPath('data.created', false);
        $this->getJson('/api/v1/customer/activities/following')->assertOk()->assertJsonPath('data.0.ulid', $activity->ulid)->assertJsonMissing(['public_email' => 'customer@example.test']);
        $this->deleteJson("/api/v1/customer/activities/{$activity->ulid}/follow")->assertOk();

        $reservation = Reservation::query()->create(['ulid' => (string) Str::ulid(), 'user_id' => $customer->id, 'activity_id' => $activity->id, 'service_id' => $service->id, 'starts_at' => now()->subDay(), 'party_size' => 2, 'status' => ReservationStatus::Completed, 'contact_phone' => '+393331234567', 'idempotency_key' => 'reviewable']);
        $this->actingAs($other, 'sanctum')->postJson("/api/v1/activities/{$activity->ulid}/reviews", ['reservation_ulid' => $reservation->ulid, 'rating' => 5, 'would_recommend' => true])->assertNotFound();
        $this->actingAs($customer, 'sanctum')->postJson("/api/v1/activities/{$activity->ulid}/reviews", ['reservation_ulid' => $reservation->ulid, 'rating' => 5, 'comment' => 'Ottimo', 'would_recommend' => true])->assertCreated();
        $this->getJson("/api/v1/activities/{$activity->ulid}/reviews")->assertOk()
            ->assertJsonPath('data.0.customer_name', $customer->name)
            ->assertJsonMissing(['event_id' => $activity->id]);
        $this->assertDatabaseHas('analytics_events', ['type' => 'activity_followed', 'activity_id' => $activity->id]);
        $this->assertDatabaseHas('analytics_events', ['type' => 'activity_unfollowed', 'activity_id' => $activity->id]);
        $this->assertDatabaseHas('analytics_events', ['type' => 'activity_review_created', 'activity_id' => $activity->id]);
        $this->actingAs($customer, 'sanctum')->withHeader('X-Analytics-Session', 'activity-session')->getJson("/api/v1/activities/{$activity->ulid}")->assertOk();
        $this->withHeader('X-Analytics-Session', 'activity-session')->getJson("/api/v1/activities/{$activity->ulid}")->assertOk();
        $this->assertSame(1, AnalyticsEvent::query()->where('type', 'activity_detail_view')->count());
    }

    public function test_public_directory_filters_and_excludes_unverified_organizers(): void
    {
        [, $activity] = $this->bookableDomain();
        [, $hiddenProfile, $hiddenMunicipality] = $this->organizerDomain('Hidden');
        $hiddenProfile->update(['verification_status' => 'pending']);
        $hidden = Activity::query()->create([...$this->activityPayload($hiddenMunicipality), 'ulid' => (string) Str::ulid(), 'organizer_profile_id' => $hiddenProfile->id, 'slug' => 'hidden-'.Str::lower(Str::random(8)), 'status' => ActivityStatus::Published]);

        $this->getJson('/api/v1/activities?q=Aurora&type=restaurant&municipality_id='.$activity->municipality_id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.ulid', $activity->ulid);
        $this->getJson('/api/v1/activities?q=inesistente')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/activities/{$hidden->ulid}")->assertNotFound();
    }

    public function test_public_directory_includes_verified_published_activity_without_booking_services(): void
    {
        [, $profile, $municipality] = $this->organizerDomain('Showcase');
        $activity = Activity::query()->create([...$this->activityPayload($municipality), 'ulid' => (string) Str::ulid(), 'organizer_profile_id' => $profile->id, 'slug' => 'showcase-'.Str::lower(Str::random(8)), 'status' => ActivityStatus::Published, 'booking_enabled' => false]);

        $this->getJson('/api/v1/activities?q=Aurora')->assertOk()
            ->assertJsonFragment(['ulid' => $activity->ulid, 'booking_enabled' => false]);
    }

    private function bookableDomain(int $capacity = 6, int $minimumAdvance = 0): array
    {
        [$organizer, $profile, $municipality] = $this->organizerDomain('Bookable'.Str::random(3));
        $activity = Activity::query()->create([...$this->activityPayload($municipality), 'ulid' => (string) Str::ulid(), 'organizer_profile_id' => $profile->id, 'slug' => 'ristorante-'.Str::lower(Str::random(8)), 'status' => ActivityStatus::Published]);
        $service = ActivityService::query()->create([...$this->servicePayload(), 'ulid' => (string) Str::ulid(), 'activity_id' => $activity->id, 'minimum_advance_minutes' => $minimumAdvance, 'capacity_mode' => 'covers']);
        $service->availabilityRules()->create(['weekday' => 6, 'start_time' => '12:30', 'capacity' => $capacity, 'active' => true]);

        return [$organizer, $activity, $service];
    }

    private function organizerDomain(string $suffix): array
    {
        $organizer = User::factory()->create(['role' => UserRole::Organizer, 'status' => 'active']);
        $region = Region::query()->create(['name' => 'Regione '.$suffix, 'code' => Str::upper(Str::random(3))]);
        $province = Province::query()->create(['region_id' => $region->id, 'name' => 'Provincia '.$suffix, 'code' => Str::upper(Str::random(2))]);
        $municipality = Municipality::query()->create(['province_id' => $province->id, 'name' => 'Comune '.$suffix, 'istat_code' => Str::random(6)]);
        $profile = OrganizerProfile::query()->create(['ulid' => (string) Str::ulid(), 'owner_user_id' => $organizer->id, 'name' => 'Organizer '.$suffix, 'type' => 'restaurant', 'email' => Str::lower(Str::random(8)).'@example.test', 'municipality_id' => $municipality->id, 'verification_status' => 'verified']);

        return [$organizer, $profile, $municipality];
    }

    private function activityPayload(Municipality $municipality): array
    {
        return ['name' => 'Ristorante Aurora', 'type' => ActivityType::Restaurant->value, 'description' => 'Cucina locale', 'municipality_id' => $municipality->id, 'address' => 'Via Roma 1', 'postal_code' => '86100', 'phone' => '+390874000000', 'public_email' => 'ristorante@example.test', 'booking_enabled' => true, 'status' => ActivityStatus::Published->value];
    }

    private function servicePayload(): array
    {
        return ['name' => 'Pranzo', 'type' => 'lunch', 'active' => true, 'booking_enabled' => true, 'min_party_size' => 1, 'max_party_size' => 8, 'minimum_advance_minutes' => 0, 'indicative_duration_minutes' => 90];
    }
}
