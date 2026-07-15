<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizerPlanningController extends Controller
{
    public function calendar(Request $request): JsonResponse
    {
        $profile = $request->user()->organizerProfile;
        abort_unless($profile, 403);

        $events = Event::query()
            ->with(['categories', 'organizerProfile', 'municipality'])
            ->when($request->filled('date'), fn ($query) => $query->whereDate('starts_at', $request->date('date')))
            ->when($request->filled('month'), fn ($query) => $query->whereMonth('starts_at', (int) $request->query('month')))
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Event $event) => [
                'ulid' => $event->ulid,
                'title' => $event->title,
                'starts_at' => $event->starts_at,
                'category' => $event->categories->first()?->name ?? 'altro',
                'color' => $this->categoryColor($event->categories->first()?->name),
                'organizer' => $event->organizerProfile->name,
                'municipality' => $event->municipality->name,
                'status' => $event->status->value,
            ]);

        return response()->json(['data' => $events]);
    }

    public function map(Request $request): JsonResponse
    {
        $profile = $request->user()->organizerProfile;
        abort_unless($profile, 403);

        $events = Event::query()
            ->with(['categories', 'municipality'])
            ->whereHas('municipality')
            ->orderBy('starts_at')
            ->limit(100)
            ->get()
            ->map(fn (Event $event) => [
                'ulid' => $event->ulid,
                'title' => $event->title,
                'category' => $event->categories->first()?->name ?? 'altro',
                'starts_at' => $event->starts_at,
                'municipality' => $event->municipality->name,
                'latitude' => $event->municipality->latitude,
                'longitude' => $event->municipality->longitude,
                'distance_km' => $event->municipality_id === $profile->municipality_id ? 0 : null,
            ]);

        return response()->json(['data' => $events]);
    }

    private function categoryColor(?string $category): string
    {
        $category = strtolower((string) $category);

        return match (true) {
            str_contains($category, 'bambini') => 'green',
            str_contains($category, 'cene') => 'red',
            str_contains($category, 'musica') => 'blue',
            str_contains($category, 'teatro') => 'purple',
            str_contains($category, 'sport') => 'orange',
            default => 'gray',
        };
    }
}
