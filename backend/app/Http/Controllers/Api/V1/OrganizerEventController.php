<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Events\CreateEvent;
use App\Actions\Events\PublishEvent;
use App\Actions\Events\SubmitEventForReview;
use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Models\Event;
use Illuminate\Http\JsonResponse;

class OrganizerEventController extends Controller
{
    public function index(): JsonResponse
    {
        $profile = request()->user()->organizerProfile;
        abort_unless($profile, 403);

        return response()->json(['data' => $profile->events()->with(['categories', 'ticketTypes'])->latest()->get()]);
    }

    public function store(StoreEventRequest $request, CreateEvent $createEvent): JsonResponse
    {
        $profile = $request->user()->organizerProfile;
        abort_unless($profile, 403);

        $event = $createEvent->handle($profile, $request->validated());

        return response()->json(['data' => $event->load('categories')], 201);
    }

    public function show(Event $event): JsonResponse
    {
        $this->authorize('viewOrganizer', $event);

        return response()->json(['data' => $event->load(['categories', 'ticketTypes', 'bookings.user'])]);
    }

    public function update(StoreEventRequest $request, Event $event, CreateEvent $createEvent): JsonResponse
    {
        $this->authorize('update', $event);

        $event = $createEvent->update($event, $request->validated());

        return response()->json(['data' => $event->load('categories')]);
    }

    public function submit(Event $event, SubmitEventForReview $submitEvent): JsonResponse
    {
        $this->authorize('submit', $event);

        return response()->json(['data' => $submitEvent->handle($event)]);
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
        abort_if($event->status === EventStatus::Completed, 422, 'Evento già completato.');
        $event->forceFill(['status' => EventStatus::Cancelled])->save();

        return response()->json(['data' => $event->refresh()]);
    }

    public function bookings(Event $event): JsonResponse
    {
        $this->authorize('viewOrganizer', $event);

        return response()->json(['data' => $event->bookings()->with(['user', 'items.ticketType'])->latest()->get()]);
    }
}
