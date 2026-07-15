<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Events\CancelEvent;
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
            ->when($request->filled('organizer_id'), fn ($query) => $query->where('organizer_profile_id', $request->integer('organizer_id')))
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

        return response()->json([
            'data' => $event->load([
                'organizerProfile.owner',
                'categories',
                'ticketTypes',
                'municipality',
                'bookings.user',
                'reviews.user',
                'reviewStatistic',
                'cancellation',
                'ranking',
            ]),
        ]);
    }

    public function suspend(Event $event, CancelEvent $cancelEvent): JsonResponse
    {
        $this->authorize('moderate', Event::class);

        $data = request()->validate([
            'reason_code' => ['nullable', 'string', 'max:80'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $activeBookings = $event->bookings()->whereIn('status', ['confirmed', 'checked_in'])->exists();

        if ($event->starts_at->isFuture() && $activeBookings) {
            $event = $cancelEvent->handle($event, request()->user(), [
                'reason_code' => $data['reason_code'] ?? 'ADMIN_SUSPENSION',
                'reason' => $data['reason'] ?? 'Evento sospeso dall amministrazione.',
            ], adminAction: true);
        } else {
            $event->forceFill(['status' => EventStatus::Suspended])->save();
        }

        return response()->json(['data' => $event->refresh()->load(['organizerProfile', 'cancellation'])]);
    }

    public function archive(Event $event): JsonResponse
    {
        $this->authorize('moderate', Event::class);

        $event->forceFill(['status' => EventStatus::Archived])->save();

        return response()->json(['data' => $event->refresh()->load(['organizerProfile'])]);
    }
}
