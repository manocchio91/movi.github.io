<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;

class FavoriteController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => request()->user()->favorites()->with(['categories', 'municipality', 'organizerProfile'])->latest('favorites.created_at')->get(),
        ]);
    }

    public function store(Event $event): JsonResponse
    {
        request()->user()->favorites()->syncWithoutDetaching([$event->id]);

        return response()->json(['message' => 'Esperienza salvata tra i preferiti.'], 201);
    }

    public function destroy(Event $event): JsonResponse
    {
        request()->user()->favorites()->detach($event->id);

        return response()->json(['message' => 'Esperienza rimossa dai preferiti.']);
    }
}
