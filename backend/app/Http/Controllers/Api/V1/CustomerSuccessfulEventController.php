<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\CustomerEventFormatInterest;
use App\Models\Event;
use App\Services\EventSuccessScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerSuccessfulEventController extends Controller
{
    public function index(Request $request, EventSuccessScoreService $scores): JsonResponse
    {
        $query = $this->successfulEventsQuery()
            ->when($request->filled('category_id'), fn ($q) => $q->whereHas('categories', fn ($category) => $category->whereKey($request->integer('category_id'))))
            ->when($request->filled('province_id'), fn ($q) => $q->whereHas('municipality', fn ($municipality) => $municipality->where('province_id', $request->integer('province_id'))))
            ->when($request->filled('municipality_id'), fn ($q) => $q->where('municipality_id', $request->integer('municipality_id')))
            ->when($request->filled('min_rating'), fn ($q) => $q->whereHas('successScore', fn ($score) => $score->where('average_rating', '>=', $request->float('min_rating'))))
            ->when($request->filled('organizer_id'), fn ($q) => $q->where('organizer_profile_id', $request->integer('organizer_id')));

        $events = $query->get();
        $events->each(fn (Event $event) => $event->successScore ?: $scores->calculate($event));

        $events = $events->load(['successScore', 'categories', 'municipality.province', 'organizerProfile:id,ulid,name,type']);

        if ($request->boolean('has_next_edition')) {
            $events = $events->filter(fn (Event $event) => $this->nextEditions($event)->isNotEmpty());
        }

        $events = match ($request->query('sort')) {
            'recent' => $events->sortByDesc('starts_at'),
            'rating' => $events->sortByDesc(fn (Event $event) => $event->successScore?->average_rating ?: 0),
            'fill_rate' => $events->sortByDesc(fn (Event $event) => $event->successScore?->fill_rate ?: 0),
            'recommended' => $events->sortByDesc(fn (Event $event) => $event->successScore?->recommendation_rate ?: 0),
            'next_edition' => $events->sortByDesc(fn (Event $event) => $this->nextEditions($event)->isNotEmpty() ? 1 : 0),
            default => $events->sortByDesc(fn (Event $event) => $event->successScore?->success_score ?: 0),
        };

        $perPage = min(24, max(1, (int) $request->query('per_page', 12)));

        return response()->json([
            'data' => $events->values()->take($perPage)->map(fn (Event $event) => $this->payload($event))->all(),
            'meta' => ['per_page' => $perPage, 'total' => $events->count()],
        ]);
    }

    public function show(Event $event): JsonResponse
    {
        abort_unless($this->isSuccessfulEvent($event), 404);

        return response()->json([
            'data' => [
                ...$this->payload($event->load(['categories', 'municipality.province', 'organizerProfile', 'successScore'])),
                'description' => $event->description,
                'program' => $event->program,
                'included' => $event->included,
                'important_information' => $event->important_information,
                'cancellation_policy' => $event->cancellation_policy,
                'reviews' => $event->reviews()
                    ->with('user:id,name')
                    ->where('status', 'approved')
                    ->whereNull('hidden_at')
                    ->latest('reviewed_at')
                    ->limit(10)
                    ->get()
                    ->map(fn ($review) => [
                        'author' => $review->user?->name,
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'reviewed_at' => $review->reviewed_at,
                    ]),
                'next_editions' => $this->nextEditions($event)->map(fn (Event $edition) => $this->editionPayload($edition))->values(),
                'similar_events' => $this->similarEvents($event)->map(fn (Event $similar) => $this->editionPayload($similar))->values(),
            ],
        ]);
    }

    public function nextEditionsEndpoint(Event $event): JsonResponse
    {
        abort_unless($this->isSuccessfulEvent($event), 404);

        return response()->json(['data' => $this->nextEditions($event)->map(fn (Event $edition) => $this->editionPayload($edition))->values()]);
    }

    public function similar(Event $event): JsonResponse
    {
        abort_unless($this->isSuccessfulEvent($event), 404);

        return response()->json(['data' => $this->similarEvents($event)->map(fn (Event $similar) => $this->editionPayload($similar))->values()]);
    }

    public function notifySimilar(Event $event, Request $request): JsonResponse
    {
        abort_unless($this->isSuccessfulEvent($event), 404);

        $category = $event->categories()->first();

        $interest = CustomerEventFormatInterest::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'event_id' => $event->id],
            [
                'event_series_id' => $event->event_series_id,
                'category_id' => $category?->id,
                'organizer_profile_id' => $event->organizer_profile_id,
                'municipality_id' => $event->municipality_id,
                'notify_similar' => true,
            ],
        );

        if ($category) {
            $request->user()->preferredCategories()->syncWithoutDetaching([$category->id]);
        }

        return response()->json(['data' => $interest], 201);
    }

    public function deleteNotifySimilar(Event $event, Request $request): JsonResponse
    {
        $request->user()->eventFormatInterests()->where('event_id', $event->id)->delete();

        return response()->json(['message' => 'Preferenza rimossa.']);
    }

    private function successfulEventsQuery()
    {
        return Event::query()
            ->with(['categories', 'municipality.province', 'organizerProfile:id,ulid,name,type', 'successScore'])
            ->where('status', EventStatus::Completed->value)
            ->where('show_in_inspiration_feed', true)
            ->whereHas('successScore')
            ->whereDoesntHave('organizerProfile', fn ($query) => $query->where('verification_status', '!=', 'verified'));
    }

    private function isSuccessfulEvent(Event $event): bool
    {
        return $event->status === EventStatus::Completed
            && $event->show_in_inspiration_feed
            && $event->successScore()->exists()
            && $event->organizerProfile?->verification_status === 'verified';
    }

    private function payload(Event $event): array
    {
        $nextEdition = $this->nextEditions($event)->first();

        return [
            'ulid' => $event->ulid,
            'title' => $event->title,
            'summary' => $event->summary,
            'category' => $event->categories->first()?->name,
            'organizer' => [
                'ulid' => $event->organizerProfile?->ulid,
                'name' => $event->organizerProfile?->name,
                'type' => $event->organizerProfile?->type,
            ],
            'municipality' => $event->municipality?->name,
            'province' => $event->municipality?->province?->name,
            'held_at' => $event->starts_at,
            'average_rating' => (float) ($event->successScore?->average_rating ?: 0),
            'review_count' => (int) ($event->successScore?->review_count ?: 0),
            'fill_rate' => (float) ($event->successScore?->fill_rate ?: 0),
            'recommendation_rate' => (float) ($event->successScore?->recommendation_rate ?: 0),
            'success_score' => (float) ($event->successScore?->success_score ?: 0),
            'success_reasons' => ['riempimento', 'recensioni positive', 'partecipazione effettiva'],
            'badge' => 'Evento di successo',
            'has_next_edition' => $nextEdition !== null,
            'next_edition' => $nextEdition ? $this->editionPayload($nextEdition) : null,
        ];
    }

    private function editionPayload(Event $event): array
    {
        return [
            'ulid' => $event->ulid,
            'title' => $event->title,
            'starts_at' => $event->starts_at,
            'available_total' => $event->available_total,
            'municipality' => $event->municipality?->name,
            'category' => $event->categories->first()?->name,
            'bookable' => in_array($event->status, [EventStatus::Published, EventStatus::SoldOut], true) && $event->starts_at->isFuture(),
        ];
    }

    private function nextEditions(Event $event)
    {
        if (! $event->event_series_id) {
            return collect();
        }

        return Event::query()
            ->with(['categories', 'municipality'])
            ->where('event_series_id', $event->event_series_id)
            ->whereKeyNot($event->id)
            ->whereIn('status', [EventStatus::Published->value, EventStatus::SoldOut->value])
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->limit(3)
            ->get();
    }

    private function similarEvents(Event $event)
    {
        $categoryIds = $event->categories()->pluck('categories.id');

        return Event::query()
            ->with(['categories', 'municipality'])
            ->whereKeyNot($event->id)
            ->whereIn('status', [EventStatus::Published->value, EventStatus::SoldOut->value])
            ->where('starts_at', '>', now())
            ->where('municipality_id', $event->municipality_id)
            ->whereHas('categories', fn ($category) => $category->whereIn('categories.id', $categoryIds))
            ->orderBy('starts_at')
            ->limit(6)
            ->get();
    }
}
