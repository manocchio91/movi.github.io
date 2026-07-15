<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiEventSuggestion;
use App\Models\Category;
use App\Models\Event;
use App\Services\OrganizerOpportunityEngine;
use App\Services\AiEventSuggestionService;
use App\Services\EventSuccessScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrganizerOpportunityController extends Controller
{
    public function index(Request $request, OrganizerOpportunityEngine $engine, AiEventSuggestionService $suggestions, EventSuccessScoreService $scores): JsonResponse
    {
        $profile = $request->user()->organizerProfile;
        abort_unless($profile, 403);

        if (! $request->filled('starts_at')) {
            return response()->json([
                'suggestions' => $suggestions->latestOrGenerate($profile),
                'top_events' => $this->topEventsPayload($scores),
            ]);
        }

        $input = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'municipality_id' => ['nullable', 'exists:municipalities,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        return response()->json(['data' => $engine->analyze($profile, $input)]);
    }

    public function generate(Request $request, AiEventSuggestionService $suggestions): JsonResponse
    {
        $profile = $request->user()->organizerProfile;
        abort_unless($profile, 403);

        $input = $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'min:2024', 'max:2035'],
        ]);

        return response()->json(['data' => $suggestions->generate($profile, $input)], 201);
    }

    public function topEvents(EventSuccessScoreService $scores): JsonResponse
    {
        return response()->json(['data' => $this->topEventsPayload($scores)]);
    }

    public function useSuggestion(AiEventSuggestion $suggestion, Request $request): JsonResponse
    {
        $profile = $request->user()->organizerProfile;
        abort_unless($suggestion->organizer_profile_id === $profile->id, 403);

        $index = (int) $request->input('index', 0);
        $item = $suggestion->suggestions['suggestions'][$index] ?? null;
        abort_unless($item, 404, 'Suggerimento non trovato.');

        $event = $this->createDraftFromIdea($profile, [
            'title' => $item['title'],
            'summary' => $item['reason'],
            'description' => 'Bozza generata da suggerimento. Personalizza testi, programma e contenuti prima della pubblicazione.',
            'starts_at' => $item['recommended_date'],
            'category' => $item['category'] ?? null,
            'price_min' => $item['suggested_price_range']['min'] ?? 0,
            'price_max' => $item['suggested_price_range']['max'] ?? 0,
            'target' => $item['target'] ?? null,
        ]);

        return response()->json(['data' => $event->load(['categories', 'ticketTypes'])], 201);
    }

    public function inspire(Event $event, Request $request): JsonResponse
    {
        $profile = $request->user()->organizerProfile;
        abort_unless($event->show_in_inspiration_feed, 404);

        $category = $event->categories()->first()?->name;
        $draft = $this->createDraftFromIdea($profile, [
            'title' => 'Bozza ispirata a un format di successo',
            'summary' => 'Struttura generica ispirata a un evento performante, senza copiare testi, immagini o dati del locale.',
            'description' => 'Crea contenuti originali per il tuo pubblico. Format suggerito: '.$category,
            'starts_at' => now()->addMonth()->toDateString(),
            'category' => $category,
            'price_min' => (int) round($event->ticketTypes()->min('price_cents') / 100),
            'price_max' => (int) round($event->ticketTypes()->max('price_cents') / 100),
            'target' => $event->family_friendly ? 'famiglie' : 'adulti e gruppi',
            'capacity' => min($event->capacity_total, 80),
        ]);

        return response()->json(['data' => $draft->load(['categories', 'ticketTypes'])], 201);
    }

    private function topEventsPayload(EventSuccessScoreService $scores): array
    {
        $events = Event::query()
            ->with(['categories', 'municipality.province', 'organizerProfile:id,type,municipality_id', 'ticketTypes', 'successScore'])
            ->where('show_in_inspiration_feed', true)
            ->whereIn('status', ['published', 'sold_out', 'completed'])
            ->limit(30)
            ->get();

        $events->each(fn (Event $event) => $event->successScore ?: $scores->calculate($event));

        return $events->load('successScore')
            ->sortByDesc(fn (Event $event) => $event->successScore?->success_score ?? 0)
            ->take(10)
            ->map(fn (Event $event) => [
                'ulid' => $event->ulid,
                'title' => $event->title,
                'category' => $event->categories->first()?->name,
                'month' => $event->starts_at->format('Y-m'),
                'organizer_type' => $event->organizerProfile?->type,
                'territory' => $event->municipality?->province?->name ?? $event->municipality?->name,
                'price_band' => $this->priceBand((int) $event->ticketTypes->min('price_cents'), (int) $event->ticketTypes->max('price_cents')),
                'capacity_total' => $event->capacity_total,
                'fill_rate' => (float) $event->successScore->fill_rate,
                'average_rating' => (float) $event->successScore->average_rating,
                'review_count' => (int) $event->successScore->review_count,
                'success_score' => (float) $event->successScore->success_score,
                'success_reasons' => ['riempimento', 'recensioni', 'partecipazione effettiva'],
                'format' => $event->categories->first()?->name,
            ])
            ->values()
            ->all();
    }

    private function createDraftFromIdea($profile, array $idea): Event
    {
        $category = Category::query()
            ->when($idea['category'] ?? null, fn ($query, $name) => $query->where('name', 'like', "%{$name}%"))
            ->first()
            ?? Category::query()->first();
        $capacity = $idea['capacity'] ?? max(10, (int) round((float) $profile->events()->avg('capacity_total') ?: 30));
        $priceCents = (int) (($idea['price_min'] ?? 0) * 100);

        $event = $profile->events()->create([
            'ulid' => (string) Str::ulid(),
            'municipality_id' => $profile->municipality_id,
            'title' => Str::limit($idea['title'], 170, ''),
            'slug' => Str::slug($idea['title']).'-'.Str::lower(Str::random(6)),
            'summary' => Str::limit($idea['summary'], 260, ''),
            'description' => $idea['description'],
            'program' => 'Da definire prima della pubblicazione.',
            'included' => 'Da confermare.',
            'important_information' => 'Bozza non pubblicata automaticamente.',
            'status' => 'draft',
            'starts_at' => $idea['starts_at'].' 20:00:00',
            'capacity_total' => $capacity,
            'available_total' => $capacity,
            'is_free' => $priceCents === 0,
            'price_notes' => $priceCents === 0 ? 'Da definire' : 'Fascia prezzo suggerita',
            'family_friendly' => str_contains(strtolower((string) ($idea['target'] ?? '')), 'fam'),
            'indoor_outdoor' => 'mixed',
        ]);
        $event->categories()->sync($category ? [$category->id] : []);
        $event->ticketTypes()->create([
            'name' => 'Ingresso',
            'audience' => 'generic',
            'max_quantity' => $capacity,
            'price_cents' => $priceCents,
            'is_active' => true,
        ]);

        return $event;
    }

    private function priceBand(int $min, int $max): string
    {
        return sprintf('%d-%d euro', (int) round($min / 100), (int) round($max / 100));
    }
}
