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
        $events = $this->publicEvents($profile, $filters, $start, $end)->get()->groupBy(fn (Event $event) => $event->starts_at->toDateString());

        return collect(CarbonPeriod::create($start, $end))->map(function ($date) use ($events): array {
            $dayEvents = $events->get($date->toDateString(), collect());
            $count = $dayEvents->count();

            return [
                'date' => $date->toDateString(),
                'events_count' => $count,
                'crowding_level' => $this->level($count),
                'color' => $this->color($count),
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
            ->where('organizer_profile_id', '!=', $profile->id)
            ->whereIn('status', [EventStatus::Published->value, EventStatus::SoldOut->value])
            ->whereBetween('starts_at', [$start, $end])
            ->when($filters['municipality_id'] ?? null, fn ($query, $id) => $query->where('municipality_id', $id))
            ->when(($filters['province_id'] ?? null) && ! ($filters['municipality_id'] ?? null), fn ($query, $id) => $query->whereHas('municipality', fn ($municipality) => $municipality->where('province_id', $id)))
            ->when($filters['category_id'] ?? null, fn ($query, $id) => $query->whereHas('categories', fn ($category) => $category->whereKey($id)))
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

    private function level(int $count): string
    {
        return match (true) {
            $count === 0 => 'empty',
            $count === 1 => 'low',
            default => 'crowded',
        };
    }

    private function color(int $count): string
    {
        return match ($this->level($count)) {
            'empty' => 'green',
            'low' => 'yellow',
            default => 'red',
        };
    }
}
