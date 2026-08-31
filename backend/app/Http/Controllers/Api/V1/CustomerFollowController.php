<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerFollowingResource;
use App\Jobs\UpdateOrganizerFollowers;
use App\Models\OrganizerProfile;
use App\Services\Analytics\AnalyticsRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerFollowController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $following = request()->user()->followedOrganizers()
            ->with('municipality')
            ->latest('organizer_followers.created_at')
            ->get();

        return CustomerFollowingResource::collection($following);
    }

    public function store(OrganizerProfile $organizer): JsonResponse
    {
        abort_unless($organizer->verification_status === 'verified', 404);

        $attached = request()->user()->followedOrganizers()->syncWithoutDetaching([$organizer->id]);
        if ($attached['attached'] !== []) {
            app(AnalyticsRecorder::class)->record('organizer_followed', user: request()->user(), organizerProfileId: $organizer->id);
        }
        UpdateOrganizerFollowers::dispatch($organizer->id);

        return response()->json([
            'message' => 'Organizzatore seguito.',
            'data' => ['is_following' => true, 'created' => $attached['attached'] !== []],
        ]);
    }

    public function destroy(OrganizerProfile $organizer): JsonResponse
    {
        $removed = request()->user()->followedOrganizers()->detach($organizer->id);
        if ($removed > 0) {
            app(AnalyticsRecorder::class)->record('organizer_unfollowed', user: request()->user(), organizerProfileId: $organizer->id);
        }
        UpdateOrganizerFollowers::dispatch($organizer->id);

        return response()->json([
            'message' => 'Organizzatore rimosso dai seguiti.',
            'data' => ['is_following' => false, 'removed' => $removed > 0],
        ]);
    }
}
