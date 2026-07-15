<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\OrganizerProfile;
use Illuminate\Http\JsonResponse;

class OrganizerPublicController extends Controller
{
    public function show(OrganizerProfile $organizer): JsonResponse
    {
        return response()->json([
            'data' => $organizer->load(['municipality', 'followers', 'subscription.plan']),
            'future_experiences' => $organizer->events()
                ->where('status', EventStatus::Published->value)
                ->where('starts_at', '>=', now())
                ->with(['categories', 'ticketTypes'])
                ->orderBy('starts_at')
                ->get(),
            'completed_experiences' => $organizer->events()
                ->whereIn('status', [EventStatus::Completed->value])
                ->latest('starts_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function follow(OrganizerProfile $organizer): JsonResponse
    {
        request()->user()->followedOrganizers()->syncWithoutDetaching([$organizer->id]);

        return response()->json(['message' => 'Organizzatore seguito.']);
    }

    public function unfollow(OrganizerProfile $organizer): JsonResponse
    {
        request()->user()->followedOrganizers()->detach($organizer->id);

        return response()->json(['message' => 'Organizzatore rimosso dai seguiti.']);
    }
}
