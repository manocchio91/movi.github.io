<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfigureAvailabilityRequest;
use App\Http\Requests\UpsertActivityServiceRequest;
use App\Http\Resources\ActivityServiceResource;
use App\Models\Activity;
use App\Models\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizerActivityServiceController extends Controller
{
    public function store(UpsertActivityServiceRequest $request, Activity $activity): JsonResponse
    {
        $this->authorize('update', $activity);
        $service = $activity->services()->create([...$request->validated(), 'ulid' => (string) Str::ulid(), 'capacity_mode' => 'covers']);

        return response()->json(['data' => ActivityServiceResource::make($service)], 201);
    }

    public function update(UpsertActivityServiceRequest $request, Activity $activity, ActivityService $service): JsonResponse
    {
        $this->authorize('update', $activity);
        abort_unless($service->activity_id === $activity->id, 404);
        $service->update($request->validated());

        return response()->json(['data' => ActivityServiceResource::make($service->refresh())]);
    }

    public function availability(ConfigureAvailabilityRequest $request, Activity $activity, ActivityService $service): JsonResponse
    {
        $this->authorize('update', $activity);
        abort_unless($service->activity_id === $activity->id, 404);
        DB::transaction(function () use ($request, $service): void {
            foreach ($request->validated('rules', []) as $rule) {
                $service->availabilityRules()->updateOrCreate(['weekday' => $rule['weekday'], 'start_time' => $rule['start_time']], ['capacity' => $rule['capacity'], 'active' => $rule['active'] ?? true]);
            }
            foreach ($request->validated('exceptions', []) as $exception) {
                $service->availabilityExceptions()->updateOrCreate(['date' => $exception['date']], ['is_closed' => $exception['is_closed'], 'slots' => $exception['slots'] ?? null, 'note' => $exception['note'] ?? null]);
            }
        });

        return response()->json(['data' => ActivityServiceResource::make($service->load(['availabilityRules', 'availabilityExceptions']))]);
    }
}
