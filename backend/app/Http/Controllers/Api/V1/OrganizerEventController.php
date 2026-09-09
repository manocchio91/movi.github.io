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
use App\Http\Requests\UploadEventImageRequest;
use App\Models\Event;
use App\Services\BusinessIntelligence\OrganizerDashboardMetrics;
use App\Services\OrganizerEventSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function store(StoreEventRequest $request, CreateEvent $createEvent, OrganizerEventSummaryService $summary): JsonResponse
    {
        $profile = $request->user()->organizerProfile;
        abort_unless($profile !== null, 403);

        $event = $createEvent->handle($profile, $request->validated());
        $event->load(['categories', 'ticketTypes', 'municipality']);

        return response()->json(['data' => $this->eventData($event, $summary)], 201);
    }

    public function show(Event $event, OrganizerEventSummaryService $summary): JsonResponse
    {
        $this->authorize('viewOrganizer', $event);

        $event->load(['categories', 'ticketTypes', 'municipality']);

        return response()->json(['data' => $this->eventData($event, $summary)]);
    }

    public function update(UpdateEventRequest $request, Event $event, UpdateEvent $updateEvent, OrganizerEventSummaryService $summary): JsonResponse
    {
        $event = $updateEvent->handle($event, $request->validated());

        return response()->json(['data' => $this->eventData($event, $summary)]);
    }

    public function image(UploadEventImageRequest $request, Event $event): JsonResponse
    {
        $disk = (string) config('filesystems.public', 'public');
        $path = $request->file('image')->store('events/'.$event->getAttribute('ulid'), $disk);
        $oldPath = $event->getAttribute('image_path');
        $event->forceFill(['image_path' => $path])->save();
        if ($oldPath) {
            Storage::disk($disk)->delete($oldPath);
        }

        return response()->json(['data' => ['image_url' => Storage::disk($disk)->url($path)]]);
    }

    public function destroyImage(Event $event): JsonResponse
    {
        $this->authorize('update', $event);
        $disk = (string) config('filesystems.public', 'public');
        $oldPath = $event->getAttribute('image_path');
        $event->forceFill(['image_path' => null])->save();
        if ($oldPath) {
            Storage::disk($disk)->delete($oldPath);
        }

        return response()->json(['data' => ['image_url' => null]]);
    }

    public function publish(Event $event, PublishEvent $publishEvent, OrganizerEventSummaryService $summary): JsonResponse
    {
        $this->authorize('viewOrganizer', $event);

        $published = $publishEvent->handle($event)->load(['categories', 'ticketTypes', 'municipality']);

        return response()->json(['data' => $this->eventData($published, $summary)]);
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

    public function participants(Request $request, Event $event): JsonResponse|StreamedResponse
    {
        $this->authorize('viewOrganizer', $event);
        $validated = validator($request->query(), ['search' => ['sometimes', 'string', 'max:120'], 'filter' => ['sometimes', 'in:all,pending,checked_in'], 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'], 'format' => ['sometimes', 'in:json,csv']])->validate();
        $query = $event->bookings()->with(['user:id,name,email', 'checkIns', 'items.ticketType'])->whereIn('status', ['confirmed', 'checked_in', 'completed']);
        $search = trim((string) ($validated['search'] ?? ''));
        $query->when($search !== '', fn ($bookings) => $bookings->where(fn ($nested) => $nested->where('booking_code', 'ilike', "%{$search}%")->orWhereHas('user', fn ($users) => $users->where('name', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"))));
        $filter = $validated['filter'] ?? 'all';
        $query->when($filter === 'checked_in', fn ($bookings) => $bookings->whereHas('checkIns'))->when($filter === 'pending', fn ($bookings) => $bookings->whereDoesntHave('checkIns'))->latest('booked_at');
        $serialize = fn ($booking) => ['booking_ulid' => $booking->ulid, 'booking_code' => $booking->booking_code, 'name' => $booking->user->name, 'email' => $booking->user->email, 'phone' => $booking->contact_phone, 'tickets' => $booking->items->map(fn ($item) => "{$item->ticketType->name} x {$item->quantity}")->implode(', '), 'people' => $booking->total_people, 'status' => $booking->status, 'checked_in' => $booking->checkIns->isNotEmpty(), 'checked_in_at' => $booking->checkIns->first()?->checked_in_at];
        if (($validated['format'] ?? 'json') === 'csv') {
            $rows = $query->get()->map($serialize);

            return response()->streamDownload(function () use ($rows): void {
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Codice', 'Cliente', 'Email', 'Telefono', 'Biglietti', 'Persone', 'Stato', 'Check-in']);
                foreach ($rows as $row) {
                    fputcsv($output, [$row['booking_code'], $row['name'], $row['email'], $row['phone'], $row['tickets'], $row['people'], $row['status'], $row['checked_in_at']]);
                }
                fclose($output);
            }, 'partecipanti-'.$event->getAttribute('ulid').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }
        $totals = $event->bookings()->whereIn('status', ['confirmed', 'checked_in', 'completed']);
        $page = $query->paginate((int) ($validated['per_page'] ?? 15));

        return response()->json(['data' => $page->getCollection()->map($serialize)->values(), 'summary' => ['bookings_total' => (clone $totals)->count(), 'people_total' => (int) (clone $totals)->sum('total_people'), 'checked_in_total' => (int) (clone $totals)->whereHas('checkIns')->sum('total_people')], 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'from' => $page->firstItem(), 'to' => $page->lastItem()]]);
    }

    /** @return array<string, mixed> */
    private function eventData(Event $event, OrganizerEventSummaryService $summary): array
    {
        $imagePath = $event->getAttribute('image_path');

        return $event->getAttributes() + [
            'image_url' => $imagePath ? Storage::disk((string) config('filesystems.public', 'public'))->url($imagePath) : null,
            'categories' => $event->categories,
            'ticket_types' => $event->ticketTypes,
            'municipality' => $event->municipality,
            'summary_metrics' => $summary->summarize($event),
        ];
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
