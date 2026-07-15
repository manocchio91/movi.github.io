<?php

namespace App\Services;

use App\Models\AiEventSuggestion;
use App\Models\Category;
use App\Models\OrganizerProfile;
use App\Models\SeasonalEventTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class AiEventSuggestionService
{
    public function generate(OrganizerProfile $profile, array $filters = []): AiEventSuggestion
    {
        $month = (int) ($filters['month'] ?? now()->month);
        $year = (int) ($filters['year'] ?? now()->year);
        $territory = $profile->municipality?->name ?? 'Molise';
        $context = $this->context($profile, $month, $year, $territory);
        $suggestions = $this->fallbackSuggestions($profile, $context);

        return AiEventSuggestion::query()->updateOrCreate(
            [
                'organizer_profile_id' => $profile->id,
                'generated_for_month' => sprintf('%04d-%02d', $year, $month),
                'generated_for_territory' => $territory,
            ],
            [
                'ulid' => (string) Str::ulid(),
                'input_context' => $context,
                'suggestions' => ['suggestions' => $suggestions],
                'provider' => 'fallback',
                'model' => null,
                'prompt_version' => 'organizer-opportunities-v1',
                'status' => 'completed',
                'latency_ms' => 0,
                'cost_cents' => 0,
                'generated_at' => now(),
                'expires_at' => now()->addDays(14),
            ],
        );
    }

    public function latestOrGenerate(OrganizerProfile $profile): AiEventSuggestion
    {
        return AiEventSuggestion::query()
            ->where('organizer_profile_id', $profile->id)
            ->where('expires_at', '>', now())
            ->latest('generated_at')
            ->first()
            ?? $this->generate($profile);
    }

    public function context(OrganizerProfile $profile, int $month, int $year, string $territory): array
    {
        $events = $profile->events()->with(['categories', 'ticketTypes'])->get();
        $bestCategory = $events->flatMap(fn ($event) => $event->categories)->countBy('name')->sortDesc()->keys()->first();
        $averageCapacity = (int) round((float) $events->avg('capacity_total'));
        $averagePrice = (int) round((float) $events->flatMap(fn ($event) => $event->ticketTypes)->avg('price_cents') / 100);

        return [
            'organizer_type' => $profile->type,
            'territory' => $territory,
            'month' => CarbonImmutable::create($year, $month, 1)->locale('it')->translatedFormat('F'),
            'available_dates' => $this->availableDates($month, $year),
            'popular_categories' => Category::query()->where('is_active', true)->limit(5)->pluck('name')->all(),
            'organizer_history' => [
                'best_category' => $bestCategory,
                'average_capacity' => $averageCapacity,
                'average_price' => $averagePrice,
            ],
        ];
    }

    private function fallbackSuggestions(OrganizerProfile $profile, array $context): array
    {
        $monthNumber = (int) CarbonImmutable::parse($context['available_dates'][0])->month;
        $templates = SeasonalEventTemplate::query()
            ->where('active', true)
            ->where('month_from', '<=', $monthNumber)
            ->where('month_to', '>=', $monthNumber)
            ->when($profile->type, fn ($query) => $query->where(fn ($nested) => $nested->whereNull('organizer_type')->orWhere('organizer_type', $profile->type)))
            ->limit(3)
            ->get();

        if ($templates->isEmpty()) {
            $templates = collect([
                new SeasonalEventTemplate([
                    'title' => $this->seasonalTitle($monthNumber),
                    'target' => 'clienti locali',
                    'suggested_price_min' => 20,
                    'suggested_price_max' => 35,
                    'suggested_description' => 'Format stagionale suggerito in base al periodo e al territorio.',
                ]),
            ]);
        }

        return $templates->values()->map(fn (SeasonalEventTemplate $template, int $index) => [
            'title' => $template->title,
            'recommended_date' => $context['available_dates'][$index] ?? $context['available_dates'][0],
            'target' => $template->target ?? 'pubblico locale',
            'category' => $template->category?->name ?? ($context['organizer_history']['best_category'] ?? 'esperienze locali'),
            'reason' => 'Suggerimento fallback basato su stagione, territorio, categorie attive e storico aggregato del locale.',
            'suggested_price_range' => [
                'min' => (int) $template->suggested_price_min,
                'max' => (int) $template->suggested_price_max,
            ],
            'confidence' => 0.72,
        ])->all();
    }

    private function availableDates(int $month, int $year): array
    {
        $first = CarbonImmutable::create($year, $month, 1);

        return collect(range(0, 27))
            ->map(fn (int $offset) => $first->addDays($offset))
            ->filter(fn (CarbonImmutable $date) => $date->isWeekend())
            ->take(3)
            ->map(fn (CarbonImmutable $date) => $date->toDateString())
            ->values()
            ->all();
    }

    private function seasonalTitle(int $month): string
    {
        return match ($month) {
            2 => 'Cena di Carnevale con animazione',
            3, 4, 5 => 'Pranzo di primavera con laboratorio floreale',
            6, 7, 8 => 'Aperitivo al tramonto',
            10 => 'Degustazione di vino novello',
            11 => 'Cena con prodotti autunnali',
            12 => 'Pranzo di Natale del territorio',
            default => 'Esperienza stagionale nel territorio',
        };
    }
}
