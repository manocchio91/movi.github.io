<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexEventRequest;
use App\Models\Event;
use App\Services\Analytics\AnalyticsRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(IndexEventRequest $request, AnalyticsRecorder $analytics): JsonResponse
    {
        $search = trim((string) $request->validated('q', ''));

        $events = Event::query()
            ->with(['categories', 'municipality', 'organizerProfile', 'ticketTypes'])
            ->whereIn('status', [EventStatus::Published, EventStatus::SoldOut])
            ->when($search !== '', fn ($q) => $q->where(fn ($event) => $event
                ->where('title', 'ilike', "%{$search}%")
                ->orWhere('description', 'ilike', "%{$search}%")))
            ->when($request->filled('category_id'), fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereKey($request->integer('category_id'))))
            ->when($request->filled('province_id'), fn ($q) => $q->whereHas('municipality', fn ($m) => $m->where('province_id', $request->integer('province_id'))))
            ->when($request->filled('municipality_id'), fn ($q) => $q->where('municipality_id', $request->integer('municipality_id')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('starts_at', $request->date('date')))
            ->when($request->boolean('free'), fn ($q) => $q->where('is_free', true))
            ->when($request->boolean('available'), fn ($q) => $q->where('available_total', '>', 0))
            ->orderBy('starts_at')
            ->paginate(15);

        $events->through(fn (Event $event) => $this->publicEvent($event));
        foreach ($events->getCollection() as $event) {
            $analytics->recordDiscovery($event, 'event_impression', $request->user(), $this->analyticsSession($request));
        }

        return response()->json($events);
    }

    public function show(Event $event, AnalyticsRecorder $analytics): JsonResponse
    {
        abort_unless(in_array($event->status, [EventStatus::Published, EventStatus::SoldOut], true), 404);
        $analytics->recordDiscovery($event, 'event_detail_view', request()->user(), $this->analyticsSession(request()));

        return response()->json([
            'data' => $this->publicEvent($event->load(['categories', 'municipality', 'organizerProfile', 'ticketTypes'])),
        ]);
    }

    private function analyticsSession(Request $request): ?string
    {
        $session = trim((string) $request->header('X-Analytics-Session'));

        return $session === '' ? null : mb_substr($session, 0, 128);
    }

    private function publicEvent(Event $event): Event
    {
        $event->makeHidden(['id', 'organizer_profile_id', 'municipality_id', 'venue_id', 'approved_by']);
        $event->ticketTypes->each->makeHidden(['id', 'event_id']);
        $event->organizerProfile?->makeHidden(['id', 'owner_user_id', 'municipality_id']);
        if ($event->organizerProfile) {
            $event->organizerProfile->setAttribute('logo_url', $event->organizerProfile->logo_path ? asset('storage/'.$event->organizerProfile->logo_path) : null);
            $event->organizerProfile->setAttribute('cover_url', $event->organizerProfile->cover_path ? asset('storage/'.$event->organizerProfile->cover_path) : null);
        }
        $event->setAttribute('canonical_url', config('frontend.url').'/customer/events/'.$event->ulid);
        $ticketAvailability = $event->ticketTypes->where('is_active', true)->sum(fn ($ticket) => $ticket->max_quantity === null ? $event->available_total : max(0, $ticket->max_quantity - $ticket->booked_quantity));
        $event->setAttribute('bookable_available_total', min($event->available_total, $ticketAvailability));

        return $event;
    }
}
