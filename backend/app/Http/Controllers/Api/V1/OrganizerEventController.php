<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Events\CreateEvent;
use App\Actions\Events\SubmitEventForReview;
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

        return response()->json(['data' => $profile->events()->with('categories')->latest()->get()]);
    }

    public function store(StoreEventRequest $request, CreateEvent $createEvent): JsonResponse
    {
        $profile = $request->user()->organizerProfile;
        abort_unless($profile, 403);

        $event = $createEvent->handle($profile, $request->validated());

        return response()->json(['data' => $event->load('categories')], 201);
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
}
