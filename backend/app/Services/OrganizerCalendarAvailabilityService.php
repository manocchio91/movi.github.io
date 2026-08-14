<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\OrganizerProfile;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class OrganizerCalendarAvailabilityService
{
    public function availability(OrganizerProfile $profile, array $filters): array
    {
        $month = (int) ($filters['month'] ?? now()->month);
        $year = (int) ($filters['year'] ?? now()->year);
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $end = $start->endOfMonth();
        $events = $this->publicEvents($profile, $filters, $start, $end)
            ->get()
            ->groupBy(fn (Event $event) => $event->starts_at->toDateString());
        $categoryId = isset($filters['category_id']) ? (int) $filters['category_id'] : null;

        return collect(CarbonPeriod::create($start, $end))->map(function ($date) use ($events, $categoryId): array {
            $dayEvents = $events->get($date->toDateString(), collect());
            $count = $dayEvents->count();
            $sameCategoryCount = $categoryId === null
                ? 0
                : $dayEvents->filter(fn (Event $event) => $event->categories->contains('id', $categoryId))->count();
            $level = $sameCategoryCount >= 2
                ? 'high_same_category'
                : ($count >= 1 ? 'same_municipality' : 'empty');

            return [
                'date' => $date->toDateString(),
                'events_count' => $count,
                'same_category_count' => $sameCategoryCount,
                'crowding_level' => $level,
                'color' => match ($level) {
                    'high_same_category' => 'red',
                    'same_municipality' => 'yellow',
                    default => 'none',
                },
                'events' => $dayEvents->map(fn (Event $event) => $this->publicEventPayload($event))->values(),
            ];
        })->all();
    }

    public function events(OrganizerProfile $profile, array $filters): Collection
    {
        $date = CarbonImmutable::parse($filters['date'] ?? now()->toDateString());

        return $this->publicEvents($profile, $filters, $date->startOfDay(), $date->endOfDay())
            ->get()
            ->map(fn (Event $event) => $this->publicEventPayload($event));
    }

    private function publicEvents(OrganizerProfile $profile, array $filters, CarbonImmutable $start, CarbonImmutable $end)
    {
        return Event::query()
            ->with(['categories', 'organizerProfile:id,name,type', 'municipality.province'])
            ->whereIn('status', [EventStatus::Published->value, EventStatus::SoldOut->value])
            ->whereBetween('starts_at', [$start, $end])
            ->when($filters['exclude_event_ulid'] ?? null, fn ($query, $ulid) => $query->where('ulid', '!=', $ulid))
            ->when($filters['municipality_id'] ?? null, fn ($query, $id) => $query->where('municipality_id', $id))
            ->when(($filters['province_id'] ?? null) && ! ($filters['municipality_id'] ?? null), fn ($query, $id) => $query->whereHas('municipality', fn ($municipality) => $municipality->where('province_id', $id)))
            ->orderBy('starts_at');
    }

    private function publicEventPayload(Event $event): array
    {
        return [
            'ulid' => $event->ulid,
            'title' => $event->title,
            'category' => $event->categories->first()?->name,
            'starts_at' => $event->starts_at,
            'time' => $event->starts_at->format('H:i'),
            'municipality' => $event->municipality?->name,
            'organizer' => $event->organizerProfile?->name,
            'organizer_type' => $event->organizerProfile?->type,
            'status' => $event->status->value,
            'sold_out' => $event->status === EventStatus::SoldOut,
            'distance_km' => null,
        ];
    }
}
