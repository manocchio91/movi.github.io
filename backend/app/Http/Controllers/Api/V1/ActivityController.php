<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActivityStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\ActivityService;
use App\Services\Analytics\AnalyticsRecorder;
use App\Services\Reservations\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(): JsonResponse
    {
        $activities = Activity::query()->where('status', ActivityStatus::Published)->where('booking_enabled', true)
            ->with(['municipality', 'organizerProfile', 'services' => fn ($q) => $q->where('active', true)->where('booking_enabled', true)])
            ->withCount(['followers', 'reviews'])->withAvg(['reviews' => fn ($q) => $q->where('status', 'approved')], 'rating')->paginate(15);

        return ActivityResource::collection($activities)->response();
    }

    public function show(Request $request, Activity $activity, AnalyticsRecorder $analytics): JsonResponse
    {
        abort_unless($activity->status === ActivityStatus::Published, 404);
        $analytics->recordActivityDetail($activity, $request->user(), $request->header('X-Analytics-Session'));
        $user = $request->user();
        $activity->load(['municipality', 'organizerProfile', 'services' => fn ($q) => $q->where('active', true)->where('booking_enabled', true), 'followers' => fn ($q) => $user ? $q->whereKey($user->id) : $q->whereRaw('1 = 0')])->loadCount(['followers', 'reviews'])->loadAvg(['reviews' => fn ($q) => $q->where('status', 'approved')], 'rating');

        return response()->json(['data' => ActivityResource::make($activity)]);
    }

    public function availability(Request $request, Activity $activity, ActivityService $service, AvailabilityService $availability): JsonResponse
    {
        abort_unless($activity->status === ActivityStatus::Published && $activity->booking_enabled && $service->activity_id === $activity->id && $service->active && $service->booking_enabled, 404);
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        return response()->json(['data' => $availability->forDate($service, CarbonImmutable::parse($data['date']))]);
    }
}
