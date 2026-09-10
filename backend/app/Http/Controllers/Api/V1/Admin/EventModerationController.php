<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventModerationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('moderate', Event::class);

        $events = Event::query()
            ->with(['organizerProfile.owner', 'categories', 'municipality', 'reviewStatistic'])
            ->withCount(['bookings', 'reviews'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('organizer'), fn ($query) => $query->whereHas('organizerProfile', fn ($profile) => $profile->where('ulid', $request->query('organizer'))))
            ->when($request->filled('q'), fn ($query) => $query->where('title', 'ilike', '%'.$request->query('q').'%'))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('starts_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('starts_at', '<=', $request->date('date_to')))
            ->latest('starts_at')
            ->paginate((int) $request->query('per_page', 25));

        $stats = [
            'total' => Event::query()->count(),
            'published' => Event::query()->where('status', EventStatus::Published->value)->count(),
            'cancelled' => Event::query()->where('status', EventStatus::Cancelled->value)->count(),
            'suspended' => Event::query()->where('status', EventStatus::Suspended->value)->count(),
            'archived' => Event::query()->where('status', EventStatus::Archived->value)->count(),
        ];

        return response()->json(['stats' => $stats, 'data' => $events]);
    }

    public function show(Event $event): JsonResponse
    {
        $this->authorize('moderate', Event::class);

        $event->load([
            'organizerProfile.owner:id,name,email',
            'categories',
            'ticketTypes',
            'municipality',
            'reviewStatistic',
            'cancellation',
            'ranking',
        ]);
        $bookings = $event->bookings()
            ->whereIn('status', ['confirmed', 'checked_in', 'completed'])
            ->with('user:id,name,email')
            ->latest('booked_at')
            ->get()
            ->map(fn ($booking) => [
                'ulid' => $booking->ulid,
                'booking_code' => $booking->booking_code,
                'total_people' => $booking->total_people,
                'status' => $booking->status,
                'booked_at' => $booking->booked_at,
                'user' => $booking->user?->only(['name', 'email']),
            ]);

        return response()->json([
            'data' => $event->toArray() + [
                'bookings_count' => $bookings->count(),
                'bookings' => $bookings,
            ],
        ]);
    }

    public function suspend(Event $event): JsonResponse
    {
        $this->authorize('moderate', Event::class);

        $data = request()->validate([
            'reason_code' => ['nullable', 'string', 'max:80'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_unless(in_array($event->status, [EventStatus::Published, EventStatus::SoldOut], true), 409, 'Solo un evento pubblicato può essere sospeso.');
        $event->forceFill(['status' => EventStatus::Suspended])->save();

        return response()->json(['data' => $event->refresh()->load(['organizerProfile', 'cancellation'])]);
    }

    public function archive(Event $event): JsonResponse
    {
        $this->authorize('moderate', Event::class);

        $event->forceFill(['status' => EventStatus::Archived])->save();

        return response()->json(['data' => $event->refresh()->load(['organizerProfile'])]);
    }

    public function reactivate(Event $event): JsonResponse
    {
        $this->authorize('moderate', Event::class);
        abort_unless($event->status === EventStatus::Suspended, 409, 'Solo un evento sospeso può essere riattivato.');
        $event->forceFill(['status' => $event->starts_at->isFuture() ? EventStatus::Published : EventStatus::Completed])->save();

        return response()->json(['data' => $event->refresh()->load('organizerProfile')]);
    }
}
