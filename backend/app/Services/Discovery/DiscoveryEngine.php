<?php

namespace App\Services\Discovery;

use App\Enums\ActivityStatus;
use App\Enums\EventStatus;
use App\Models\Activity;
use App\Models\Event;
use App\Models\User;
use App\Services\Reservations\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DiscoveryEngine
{
    public function __construct(private readonly AvailabilityService $availability) {}

    public function search(array $filters, ?User $user = null, bool $recommended = false): Collection
    {
        [$from, $to, $evening] = $this->window($filters);
        $profile = InterestProfile::for($user);
        $type = $filters['type'] ?? 'all';
        $rows = collect();
        if ($type !== 'activity') {
            $rows = $rows->concat($this->events($filters, $from, $to, $profile));
        }
        if ($type !== 'event' && empty($filters['category_id']) && empty($filters['family'])) {
            $rows = $rows->concat($this->activities($filters, $from, $to, $evening, $profile));
        }
        $rows = $rows->filter(fn (array $row) => ! isset($filters['radius_km']) || ($row['distance_km'] !== null && $row['distance_km'] <= (float) $filters['radius_km']));
        $sort = $recommended ? 'relevance' : ($filters['sort'] ?? 'date');

        return $rows->sortBy(fn (array $row) => match ($sort) {
            'distance' => $row['distance_km'] ?? PHP_FLOAT_MAX, 'popularity' => -$row['popularity'], 'relevance' => -$row['recommendation_score'], default => $row['starts_at']
        })->values();
    }

    private function events(array $filters, CarbonImmutable $from, CarbonImmutable $to, InterestProfile $profile): Collection
    {
        return Event::query()->with(['categories', 'municipality', 'organizerProfile', 'ticketTypes'])->whereIn('status', [EventStatus::Published, EventStatus::SoldOut])
            ->whereBetween('starts_at', [$from, $to])->when(empty($filters['include_sold_out']) || ! empty($filters['available']), fn ($q) => $q->where('available_total', '>=', (int) ($filters['party_size'] ?? 1)))
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->whereHas('categories', fn ($c) => $c->whereKey($id)))
            ->when($filters['municipality_id'] ?? null, fn ($q, $id) => $q->where('municipality_id', $id))->when($filters['family'] ?? false, fn ($q) => $q->where('family_friendly', true))
            ->limit(200)->get()->map(fn (Event $event) => $this->eventRow($event, $profile, $filters));
    }

    private function activities(array $filters, CarbonImmutable $from, CarbonImmutable $to, bool $evening, InterestProfile $profile): Collection
    {
        return Activity::query()->with(['municipality', 'organizerProfile', 'services' => fn ($q) => $q->where('active', true)->where('booking_enabled', true)])
            ->withCount('followers')->where('status', ActivityStatus::Published)->where('booking_enabled', true)
            ->when($filters['municipality_id'] ?? null, fn ($q, $id) => $q->where('municipality_id', $id))->limit(100)->get()
            ->map(function (Activity $activity) use ($from, $to, $evening, $profile, $filters): ?array {
                $slots = collect();
                for ($date = $from->startOfDay(); $date <= $to; $date = $date->addDay()) {
                    foreach ($activity->services as $service) {
                        $slots = $slots->concat(collect($this->availability->forDate($service, $date))->filter(fn (array $slot) => $slot['available'] >= (int) ($filters['party_size'] ?? 1) && (! $evening || CarbonImmutable::parse($slot['starts_at'])->hour >= 18))->map(fn (array $slot) => [...$slot, 'service_ulid' => $service->ulid, 'duration_minutes' => $service->indicative_duration_minutes]));
                    }
                }
                if (($filters['available'] ?? true) && $slots->isEmpty()) {
                    return null;
                }
                $first = $slots->sortBy('starts_at')->first();
                [$score, $reasons] = $this->score([], $activity->organizer_profile_id, $activity->id, $profile, $filters, $first !== null);

                return ['entity_type' => 'activity', 'entity_id' => $activity->ulid, 'title' => $activity->name, 'slug' => $activity->slug, 'category' => null, 'image' => null,
                    'location' => $activity->municipality?->name, 'latitude' => $activity->municipality?->latitude, 'longitude' => $activity->municipality?->longitude,
                    'starts_at' => $first['starts_at'] ?? null, 'ends_at' => $first ? CarbonImmutable::parse($first['starts_at'])->addMinutes((int) $first['duration_minutes'])->toIso8601String() : null,
                    'duration_minutes' => $first['duration_minutes'] ?? null, 'service_id' => $first['service_ulid'] ?? null, 'availability_status' => $first ? 'available' : 'unavailable', 'available' => $first['available'] ?? 0,
                    'min_price_cents' => null, 'organizer' => $activity->organizerProfile?->name, 'distance_km' => $this->distance($activity->municipality?->latitude, $activity->municipality?->longitude, $filters),
                    'recommendation_score' => $score, 'recommendation_reasons' => $reasons, 'popularity' => (int) $activity->followers_count];
            })->filter();
    }

    private function eventRow(Event $event, InterestProfile $profile, array $filters): array
    {
        $categories = $event->categories->pluck('id')->map(fn ($id) => (int) $id)->all();
        [$score, $reasons] = $this->score($categories, $event->organizer_profile_id, null, $profile, $filters, $event->available_total > 0);

        return ['entity_type' => 'event', 'entity_id' => $event->ulid, 'title' => $event->title, 'slug' => $event->slug, 'category' => $event->categories->first()?->name,
            'image' => $event->organizerProfile?->cover_path ? asset('storage/'.$event->organizerProfile->cover_path) : null, 'location' => $event->municipality?->name,
            'latitude' => $event->municipality?->latitude, 'longitude' => $event->municipality?->longitude, 'starts_at' => $event->starts_at->toIso8601String(), 'ends_at' => $event->ends_at?->toIso8601String(), 'duration_minutes' => $event->ends_at ? $event->starts_at->diffInMinutes($event->ends_at) : null, 'service_id' => null,
            'availability_status' => $event->available_total > 0 ? 'available' : 'sold_out', 'available' => $event->available_total,
            'min_price_cents' => $event->ticketTypes->where('is_active', true)->min('price_cents'), 'organizer' => $event->organizerProfile?->name,
            'distance_km' => $this->distance($event->municipality?->latitude, $event->municipality?->longitude, $filters), 'recommendation_score' => $score,
            'recommendation_reasons' => $reasons, 'popularity' => max(0, $event->capacity_total - $event->available_total)];
    }

    private function score(array $categories, int $organizer, ?int $activity, InterestProfile $profile, array $filters, bool $available): array
    {
        $weights = config('discovery.weights');
        $score = 0;
        $reasons = [];
        if (array_intersect($categories, $profile->categoryIds)) {
            $score += $weights['preferred_category'];
            $reasons[] = 'Categoria che segui';
        }
        if (in_array($organizer, $profile->organizerIds, true)) {
            $score += $weights['followed_organizer'];
            $reasons[] = 'Organizer che segui';
        }
        if ($activity && in_array($activity, $profile->activityIds, true)) {
            $score += $weights['followed_activity'];
            $reasons[] = 'Attività che segui';
        }
        if ($available) {
            $score += $weights['available'];
            $reasons[] = isset($filters['when']) ? 'Disponibile nel periodo scelto' : 'Disponibile';
        }

        return [$score, $reasons];
    }

    private function window(array $filters): array
    {
        $now = CarbonImmutable::now();
        $when = $filters['when'] ?? null;
        $evening = $when === 'evening';
        if (! empty($filters['from'])) {
            return [CarbonImmutable::parse($filters['from'])->startOfDay(), CarbonImmutable::parse($filters['to'] ?? $filters['from'])->endOfDay(), false];
        }

        $saturday = $now->startOfWeek()->addDays(5);
        if ($saturday->endOfDay()->isPast()) {
            $saturday = $saturday->addWeek();
        }

        return match ($when) {
            'today' => [$now->startOfDay(), $now->endOfDay(), false], 'evening' => [$now->setTime(18, 0), $now->endOfDay(), true], 'tomorrow' => [$now->addDay()->startOfDay(), $now->addDay()->endOfDay(), false], 'weekend' => [$saturday->startOfDay(), $saturday->addDay()->endOfDay(), false], default => [$now, $now->addDays(30)->endOfDay(), false]
        };
    }

    private function distance(mixed $lat, mixed $lon, array $filters): ?float
    {
        if ($lat === null || $lon === null || ! isset($filters['latitude'], $filters['longitude'])) {
            return null;
        }
        $lat1 = deg2rad((float) $filters['latitude']);
        $lat2 = deg2rad((float) $lat);
        $delta = deg2rad((float) $lon - (float) $filters['longitude']);

        return round(6371 * acos(min(1, max(-1, sin($lat1) * sin($lat2) + cos($lat1) * cos($lat2) * cos($delta)))), 1);
    }
}
