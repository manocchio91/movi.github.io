<?php

namespace App\Services\Reservations;

use App\Enums\ReservationStatus;
use App\Models\ActivityService;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AvailabilityService
{
    /** @return array<int, array{starts_at:string,capacity:int,reserved:int,available:int}> */
    public function forDate(ActivityService $service, CarbonImmutable $date, bool $lock = false): array
    {
        $exceptionQuery = $service->availabilityExceptions()->whereDate('date', $date->toDateString());
        $exception = ($lock ? $exceptionQuery->lockForUpdate() : $exceptionQuery)->first();
        if ($exception?->is_closed) {
            return [];
        }

        $configured = $exception?->slots !== null
            ? collect($exception->slots)
            : $this->weeklySlots($service, $date, $lock);

        return $configured->map(function (array $slot) use ($service, $date, $lock): array {
            $startsAt = CarbonImmutable::parse($date->toDateString().' '.$slot['start_time']);
            $reservations = Reservation::query()->where('service_id', $service->id)
                ->where('starts_at', $startsAt)->where('status', ReservationStatus::Confirmed);
            $reserved = $lock
                ? (int) $reservations->lockForUpdate()->get(['party_size'])->reduce(fn (int $sum, Reservation $reservation): int => $sum + $reservation->party_size, 0)
                : (int) $reservations->sum('party_size');
            $capacity = (int) $slot['capacity'];

            return ['starts_at' => $startsAt->toIso8601String(), 'capacity' => $capacity, 'reserved' => $reserved, 'available' => max(0, $capacity - $reserved)];
        })->values()->all();
    }

    /** @return Collection<int, array{start_time:string,capacity:int}> */
    private function weeklySlots(ActivityService $service, CarbonImmutable $date, bool $lock): Collection
    {
        $query = $service->availabilityRules()->where('weekday', $date->dayOfWeekIso)->where('active', true)
            ->where(fn ($rules) => $rules->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date))
            ->where(fn ($rules) => $rules->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderBy('start_time');

        return ($lock ? $query->lockForUpdate() : $query)->get()->map(fn ($rule): array => [
            'start_time' => substr((string) $rule->start_time, 0, 5),
            'capacity' => (int) $rule->capacity,
        ]);
    }
}
