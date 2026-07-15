<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $events = Event::query()
            ->with(['categories', 'municipality', 'organizerProfile'])
            ->where('status', EventStatus::Published)
            ->when($request->filled('category_id'), fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereKey($request->integer('category_id'))))
            ->when($request->filled('province_id'), fn ($q) => $q->whereHas('municipality', fn ($m) => $m->where('province_id', $request->integer('province_id'))))
            ->when($request->filled('municipality_id'), fn ($q) => $q->where('municipality_id', $request->integer('municipality_id')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('starts_at', $request->date('date')))
            ->orderBy('starts_at')
            ->paginate(15);

        return response()->json($events);
    }

    public function show(Event $event): JsonResponse
    {
        abort_unless($event->status === EventStatus::Published, 404);

        return response()->json([
            'data' => $event->load(['categories', 'municipality', 'organizerProfile']),
        ]);
    }
}
