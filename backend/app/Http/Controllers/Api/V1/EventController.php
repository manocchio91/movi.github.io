<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexEventRequest;
use App\Models\Event;
use Illuminate\Http\JsonResponse;

class EventController extends Controller
{
    public function index(IndexEventRequest $request): JsonResponse
    {
        $search = trim((string) $request->validated('q', ''));

        $events = Event::query()
            ->with(['categories', 'municipality', 'organizerProfile', 'ticketTypes'])
            ->where('status', EventStatus::Published)
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

        return response()->json($events);
    }

    public function show(Event $event): JsonResponse
    {
        abort_unless($event->status === EventStatus::Published, 404);

        return response()->json([
            'data' => $event->load(['categories', 'municipality', 'organizerProfile', 'ticketTypes']),
        ]);
    }
}
