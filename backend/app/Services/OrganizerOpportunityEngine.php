<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\OrganizerOpportunityReport;
use App\Models\OrganizerProfile;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OrganizerOpportunityEngine
{
    public function analyze(OrganizerProfile $organizer, array $input): array
    {
        $startsAt = Carbon::parse($input['starts_at']);
        $endsAt = isset($input['ends_at']) ? Carbon::parse($input['ends_at']) : $startsAt->copy()->addHours(2);
        $municipalityId = $input['municipality_id'] ?? null;
        $categoryIds = collect($input['category_ids'] ?? []);

        $events = Event::query()
            ->with(['categories', 'organizerProfile', 'municipality', 'bookings'])
            ->whereIn('status', [EventStatus::Published->value, EventStatus::SoldOut->value])
            ->whereDate('starts_at', $startsAt->toDateString())
            ->when($municipalityId, fn ($query) => $query->where('municipality_id', $municipalityId))
            ->orderBy('starts_at')
            ->get();

        $overlapping = $events->filter(fn (Event $event) => $event->starts_at < $endsAt && ($event->ends_at ?? $event->starts_at->copy()->addHours(2)) > $startsAt);
        $sameCategories = $events->filter(fn (Event $event) => $event->categories->pluck('id')->intersect($categoryIds)->isNotEmpty());

        $summary = [
            'events_same_day' => $events->count(),
            'events_same_time' => $overlapping->count(),
            'similar_category_events' => $sameCategories->count(),
            'by_category' => $this->categoryBreakdown($events),
            'competing_events' => $overlapping->map(fn (Event $event) => [
                'title' => $event->title,
                'category' => $event->categories->first()?->name ?? 'altro',
                'organizer' => $event->organizerProfile->name,
                'municipality' => $event->municipality->name,
                'distance_km' => $event->municipality_id === $municipalityId ? 0 : null,
                'starts_at' => $event->starts_at->toIso8601String(),
                'status' => $event->status->value,
                'capacity_total' => $event->capacity_total,
                'booked_places' => max(0, $event->capacity_total - $event->available_total),
            ])->values(),
        ];

        $suggestions = $this->suggestions($events, $overlapping, $sameCategories);

        OrganizerOpportunityReport::query()->create([
            'ulid' => (string) Str::ulid(),
            'organizer_profile_id' => $organizer->id,
            'municipality_id' => $municipalityId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'input' => $input,
            'summary' => $summary,
            'suggestions' => $suggestions,
            'calculated_at' => now(),
        ]);

        return ['summary' => $summary, 'suggestions' => $suggestions];
    }

    private function categoryBreakdown(Collection $events): array
    {
        return $events
            ->flatMap(fn (Event $event) => $event->categories->pluck('name'))
            ->countBy()
            ->map(fn (int $count, string $name) => ['category' => $name, 'count' => $count])
            ->values()
            ->all();
    }

    private function suggestions(Collection $events, Collection $overlapping, Collection $sameCategories): array
    {
        $suggestions = [];

        if ($events->isEmpty()) {
            $suggestions[] = 'Nessuna esperienza presente nello stesso giorno. Ottima opportunita.';
        }

        if ($sameCategories->isEmpty()) {
            $suggestions[] = 'Non risultano esperienze simili nella stessa categoria.';
        }

        if ($overlapping->isNotEmpty()) {
            $suggestions[] = 'Sono presenti esperienze nello stesso orario. Valuta di anticipare o posticipare.';
        }

        if ($events->count() >= 4) {
            $suggestions[] = 'La giornata e affollata: valuta una comunicazione piu mirata o una fascia oraria alternativa.';
        }

        return $suggestions ?: ['Slot interessante: concorrenza bassa e nessuna sovrapposizione rilevante.'];
    }
}
