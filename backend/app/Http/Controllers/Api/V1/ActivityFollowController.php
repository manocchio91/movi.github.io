<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActivityStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Services\Analytics\AnalyticsRecorder;
use Illuminate\Http\JsonResponse;

class ActivityFollowController extends Controller
{
    public function index(): JsonResponse
    {
        return ActivityResource::collection(request()->user()->followedActivities()->with(['municipality', 'organizerProfile', 'services'])->get())->response();
    }

    public function store(Activity $activity, AnalyticsRecorder $analytics): JsonResponse
    {
        abort_unless($activity->status === ActivityStatus::Published, 404);
        $result = request()->user()->followedActivities()->syncWithoutDetaching([$activity->id]);
        if ($result['attached'] !== []) {
            $analytics->recordActivity('activity_followed', $activity, request()->user());
        }

        return response()->json(['data' => ['is_following' => true, 'created' => $result['attached'] !== []]]);
    }

    public function destroy(Activity $activity, AnalyticsRecorder $analytics): JsonResponse
    {
        $removed = request()->user()->followedActivities()->detach($activity->id);
        if ($removed > 0) {
            $analytics->recordActivity('activity_unfollowed', $activity, request()->user());
        }

        return response()->json(['data' => ['is_following' => false, 'removed' => $removed > 0]]);
    }
}
