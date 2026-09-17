<?php

namespace App\Services\Planning;

use App\Models\Activity;
use App\Models\ActivityService;
use App\Models\Event;
use App\Models\User;
use App\Services\Discovery\DiscoveryEngine;
use App\Services\Reservations\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class PlanningEngine
{
    public function __construct(private readonly DiscoveryEngine $discovery, private readonly AvailabilityService $availability) {}

    public function generate(array $input, ?User $user): array
    {
        $filters = ['when' => $input['when'] ?? null, 'type' => 'all', 'available' => true, 'sort' => 'relevance'];
        if (isset($input['date'])) {
            $filters['from'] = $input['date'];
            $filters['to'] = $input['date'];
            unset($filters['when']);
        }
        foreach (['category_id', 'municipality_id', 'latitude', 'longitude', 'radius_km', 'party_size'] as $key) {
            if (isset($input[$key])) {
                $filters[$key] = $input[$key];
            }
        }
        if (($input['with'] ?? null) === 'family') {
            $filters['family'] = true;
        }
        $candidates = $this->discovery->search(array_filter($filters, fn ($value) => $value !== null), $user, true)
            ->filter(fn (array $row) => $this->withinTime($row, $input))->take((int) config('planning.candidate_limit'))->values();
        $groups = $candidates->map(fn (array $row) => [$row]);
        $maxSteps = min((int) config('planning.max_steps'), (int) ($input['max_steps'] ?? config('planning.max_steps')));
        $timed = $candidates->filter(fn (array $row) => $row['ends_at'] !== null)->sortBy('starts_at')->values();
        for ($i = 0; $i < $timed->count(); $i++) {
            for ($j = $i + 1; $j < $timed->count(); $j++) {
                $pair = [$timed[$i], $timed[$j]];
                if (! $this->compatible($pair[0], $pair[1])) {
                    continue;
                }
                if ($maxSteps >= 2) {
                    $groups->push($pair);
                }
                for ($k = $j + 1; $k < $timed->count(); $k++) {
                    if ($maxSteps >= 3 && $this->compatible($pair[1], $timed[$k])) {
                        $groups->push([$pair[0], $pair[1], $timed[$k]]);
                    }
                }
            }
        }
        $plans = $groups->map(fn (array $steps) => $this->plan($steps, $input))->filter()->sortByDesc('score');
        $seen = [];

        return $plans->filter(function (array $plan) use (&$seen): bool {
            $signature = collect($plan['steps'])->pluck('entity_id')->sort()->join(':');
            if (isset($seen[$signature])) {
                return false;
            } $seen[$signature] = true;

            return true;
        })
            ->take((int) config('planning.max_plans'))->values()->all();
    }

    public function revalidate(array $steps): array
    {
        return collect($steps)->map(function (array $step): array {
            $available = false;
            if ($step['entity_type'] === 'event') {
                $available = Event::query()->where('ulid', $step['entity_id'])->whereIn('status', ['published', 'sold_out'])->where('available_total', '>', 0)->exists();
            } else {
                $activity = Activity::query()->where('ulid', $step['entity_id'])->where('booking_enabled', true)->first();
                $service = ActivityService::query()->where('ulid', $step['service_id'] ?? '')->first();
                if ($activity && $service && $service->activity_id === $activity->id) {
                    $available = collect($this->availability->forDate($service, CarbonImmutable::parse($step['start'])))->contains(fn (array $slot) => CarbonImmutable::parse($slot['starts_at'])->equalTo(CarbonImmutable::parse($step['start'])) && $slot['available'] > 0);
                }
            }

            return [...$step, 'available' => $available];
        })->pipe(fn (Collection $rows) => ['available' => $rows->every('available'), 'steps' => $rows->all(), 'unavailable_steps' => $rows->where('available', false)->pluck('entity_id')->values()->all()]);
    }

    private function withinTime(array $row, array $input): bool
    {
        if (! isset($input['start_time']) && ! isset($input['end_time'])) {
            return true;
        }
        $start = CarbonImmutable::parse($row['starts_at']);
        $end = $row['ends_at'] ? CarbonImmutable::parse($row['ends_at']) : $start;

        return (! isset($input['start_time']) || $start->format('H:i') >= $input['start_time']) && (! isset($input['end_time']) || $end->format('H:i') <= $input['end_time']);
    }

    private function compatible(array $first, array $second): bool
    {
        $distance = $this->distance($first, $second);
        $travelMinutes = $distance === null ? 0 : (int) ceil($distance * (float) config('planning.travel_minutes_per_km'));
        $minimum = CarbonImmutable::parse($first['ends_at'])->addMinutes((int) config('planning.buffer_minutes') + $travelMinutes);
        if (CarbonImmutable::parse($second['starts_at'])->lt($minimum)) {
            return false;
        }

        return $distance === null || $distance <= (float) config('planning.max_step_distance_km');
    }

    private function plan(array $steps, array $input): ?array
    {
        $known = collect($steps)->whereNotNull('min_price_cents')->sum('min_price_cents');
        $allKnown = collect($steps)->every(fn (array $step) => $step['min_price_cents'] !== null);
        if (isset($input['budget_cents']) && $known > $input['budget_cents']) {
            return null;
        }
        $budget = ! isset($input['budget_cents']) ? 'not_requested' : ($allKnown ? 'verified' : 'partial');
        $distances = [];
        $totalDistance = 0.0;
        foreach ($steps as $index => &$step) {
            $distance = $index ? $this->distance($steps[$index - 1], $step) : null;
            $step['distance_from_previous_km'] = $distance;
            $step['start'] = $step['starts_at'];
            $step['end'] = $step['ends_at'];
            $step['price_cents'] = $step['min_price_cents'];
            $step['booking_capability'] = $step['entity_type'] === 'event' ? 'booking' : 'reservation';
            unset($step['popularity']);
            if ($distance !== null) {
                $totalDistance += $distance;
            }
        }
        $reasons = collect($steps)->pluck('recommendation_reasons')->flatten()->unique()->values();
        if (count($steps) > 1) {
            $reasons->push('Orari compatibili');
        }
        $score = collect($steps)->sum('recommendation_score') + count($steps) * (int) config('planning.weights.step') + (count($steps) > 1 ? (int) config('planning.weights.compatibility') : 0);
        $id = substr(hash('sha256', collect($steps)->pluck('entity_id')->join('|').'|'.$steps[0]['starts_at']), 0, 20);

        return ['plan_id' => $id, 'title' => count($steps).' tappe per '.CarbonImmutable::parse($steps[0]['starts_at'])->format('d/m'), 'date' => CarbonImmutable::parse($steps[0]['starts_at'])->toDateString(), 'score' => $score,
            'reasons' => $reasons->all(), 'number_of_steps' => count($steps), 'known_total_price_cents' => $allKnown ? $known : null, 'budget_verification' => $budget,
            'total_known_distance_km' => round($totalDistance, 1), 'availability_status' => 'available', 'steps' => $steps];
    }

    private function distance(array $a, array $b): ?float
    {
        if ($a['latitude'] === null || $a['longitude'] === null || $b['latitude'] === null || $b['longitude'] === null) {
            return null;
        }
        $lat1 = deg2rad((float) $a['latitude']);
        $lat2 = deg2rad((float) $b['latitude']);
        $delta = deg2rad((float) $b['longitude'] - (float) $a['longitude']);

        return round(6371 * acos(min(1, max(-1, sin($lat1) * sin($lat2) + cos($lat1) * cos($lat2) * cos($delta)))), 1);
    }
}
