<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Events\CancelEvent;
use App\Actions\Events\CreateEvent;
use App\Actions\Events\PublishEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Models\Event;
use App\Services\OrganizerEventSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizerEventController extends Controller
{
    public function index(Request $request, OrganizerEventSummaryService $summary): JsonResponse
    {
        $profile = request()->user()->organizerProfile;
        abort_unless($profile !== null, 403);

        $sort = $request->query('sort') === 'asc' ? 'asc' : 'desc';
        $events = $profile->events()
            ->with(['categories', 'ticketTypes', 'municipality', 'reviewStatistic'])
            ->when($request->filled('category_id'), fn ($query) => $query->whereHas('categories', fn ($category) => $category->whereKey($request->integer('category_id'))))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('starts_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('starts_at', '<=', $request->date('date_to')))
            ->orderBy('starts_at', $sort)
            ->get()
            ->map(fn (Event $event) => ['event' => $event, 'summary' => $summary->summarize($event)])
            ->filter(function (array $item) use ($request): bool {
                $status = $request->query('status');

                return match ($status) {
                    'open' => $item['summary']['visual_status'] === 'Aperto',
                    'closed' => $item['summary']['visual_status'] === 'Chiuso',
                    'cancelled' => $item['summary']['visual_status'] === 'Annullato',
                    'future' => $item['event']->starts_at->isFuture(),
                    default => true,
                };
            })
            ->values();

        return response()->json(['data' => $events]);
    }

    public function store(StoreEventRequest $request, CreateEvent $createEvent): JsonResponse
    {
        $profile = $request->user()->organizerProfile;
        abort_unless($profile !== null, 403);

        $event = $createEvent->handle($profile, $request->validated());

        return response()->json(['data' => $event->load('categories')], 201);
    }

    public function show(Event $event): JsonResponse
    {
        $this->authorize('viewOrganizer', $event);

        return response()->json(['data' => $event->load(['categories', 'ticketTypes', 'bookings.user', 'municipality', 'organizerProfile'])]);
    }

    public function update(StoreEventRequest $request, Event $event, CreateEvent $createEvent): JsonResponse
    {
        $this->authorize('update', $event);

        $event = $createEvent->update($event, $request->validated());

        return response()->json(['data' => $event->load('categories')]);
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
