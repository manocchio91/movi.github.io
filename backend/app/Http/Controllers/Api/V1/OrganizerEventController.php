<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Events\CancelEvent;
use App\Actions\Events\CreateEvent;
use App\Actions\Events\PublishEvent;
use App\Actions\Events\UpdateEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexOrganizerEventRequest;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use App\Services\BusinessIntelligence\OrganizerDashboardMetrics;
use App\Services\OrganizerEventSummaryService;
use Illuminate\Http\JsonResponse;

class OrganizerEventController extends Controller
{
    public function index(IndexOrganizerEventRequest $request, OrganizerEventSummaryService $summary): JsonResponse
    {
        $profile = $request->user()->organizerProfile;
        abort_unless($profile !== null, 403);

        $validated = $request->validated();
        $filter = (string) ($validated['filter'] ?? 'all');
        $sort = (string) ($validated['sort'] ?? 'asc');
        $perPage = (int) ($validated['per_page'] ?? 12);

        $query = $profile->events()
            ->with(['categories', 'ticketTypes', 'municipality', 'reviewStatistic'])
            ->when($filter === 'future', fn ($events) => $events
                ->whereIn('status', OrganizerDashboardMetrics::OPERATIONAL_EVENT_STATUSES)
                ->where('starts_at', '>=', now()))
            ->when($filter === 'completed', fn ($events) => $events->where('status', 'completed'))
            ->when($filter === 'unpublished', fn ($events) => $events->whereIn('status', OrganizerDashboardMetrics::UNPUBLISHED_EVENT_STATUSES))
            ->when($filter === 'cancelled', fn ($events) => $events->where('status', 'cancelled'))
            ->orderBy('starts_at', $sort);

        $events = $query->paginate($perPage);
        $items = $events->getCollection()->map(
            fn (Event $event) => ['event' => $event, 'summary' => $summary->summarize($event)],
        )->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
                'from' => $events->firstItem(),
                'to' => $events->lastItem(),
            ],
        ]);
    }

    public function store(StoreEventRequest $request, CreateEvent $createEvent): JsonResponse
    {
        $profile = $request->user()->organizerProfile;
        abort_unless($profile !== null, 403);

        $event = $createEvent->handle($profile, $request->validated());

        return response()->json(['data' => $event], 201);
    }

    public function show(Event $event): JsonResponse
    {
        $this->authorize('viewOrganizer', $event);

        return response()->json(['data' => $event->load(['categories', 'ticketTypes', 'municipality'])]);
    }

    public function update(UpdateEventRequest $request, Event $event, UpdateEvent $updateEvent): JsonResponse
    {
        $event = $updateEvent->handle($event, $request->validated());

        return response()->json(['data' => $event]);
    }

    public function publish(Event $event, PublishEvent $publishEvent): JsonResponse
    {
        $this->authorize('viewOrganizer', $event);

        return response()->json(['data' => $publishEvent->handle($event)->load(['categories', 'ticketTypes'])]);
    }

    public function closeBookings(Event $event): JsonResponse
    {
        $this->authorize('viewOrganizer', $event);
        $event->forceFill(['booking_closes_at' => now()])->save();

        return response()->json(['data' => $event->refresh()]);
    }

    public function cancel(Event $event): JsonResponse
    {
        $this->authorize('viewOrganizer', $event);
        $data = request()->validate([
            'reason_code' => ['required', 'string', 'max:80'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        return response()->json(['data' => app(CancelEvent::class)->handle($event, request()->user(), $data)]);
    }

    public function bookings(Event $event): JsonResponse
    {
        $this->authorize('viewOrganizer', $event);

        return response()->json(['data' => $event->bookings()->with(['user', 'items.ticketType'])->latest()->get()]);
    }

    public function summary(Event $event, OrganizerEventSummaryService $summary): JsonResponse
    {
        $this->authorize('viewOrganizer', $event);

        return response()->json(['data' => $summary->summarize($event)]);
    }

    public function participants(Event $event): JsonResponse
    {
        $this->authorize('viewOrganizer', $event);

        return response()->json([
            'data' => $event->bookings()
                ->with(['user:id,name,email'])
                ->whereIn('status', ['confirmed', 'checked_in', 'completed'])
                ->latest()
                ->get()
                ->map(fn ($booking) => [
                    'booking_code' => $booking->booking_code,
                    'name' => $booking->user->name,
                    'email' => $booking->user->email,
                    'people' => $booking->total_people,
                    'status' => $booking->status,
                    'checked_in' => in_array($booking->status, ['checked_in', 'completed'], true) || $booking->checkIns()->exists(),
                ]),
        ]);
    }

    public function reviews(Event $event): JsonResponse
    {
        $this->authorize('viewOrganizer', $event);

        return response()->json([
            'statistics' => $event->reviewStatistic,
            'data' => $event->reviews()
                ->with(['user:id,name', 'images'])
                ->where('status', 'approved')
                ->whereNull('hidden_at')
                ->latest('reviewed_at')
                ->get(),
        ]);
    }
}
