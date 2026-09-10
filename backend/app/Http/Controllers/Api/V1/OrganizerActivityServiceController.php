<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfigureAvailabilityRequest;
use App\Http\Requests\UpsertActivityServiceRequest;
use App\Http\Resources\ActivityServiceResource;
use App\Models\Activity;
use App\Models\ActivityService;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizerActivityServiceController extends Controller
{
    public function store(UpsertActivityServiceRequest $request, Activity $activity): JsonResponse
    {
        $this->authorize('update', $activity);
        $service = $activity->services()->create([...$this->serviceData($request, $activity), 'ulid' => (string) Str::ulid(), 'capacity_mode' => 'covers']);

        return response()->json(['data' => ActivityServiceResource::make($service)], 201);
    }

    public function update(UpsertActivityServiceRequest $request, Activity $activity, ActivityService $service): JsonResponse
    {
        $this->authorize('update', $activity);
        abort_unless($service->activity_id === $activity->id, 404);
        $service->update($this->serviceData($request, $activity));

        return response()->json(['data' => ActivityServiceResource::make($service->refresh())]);
    }

    public function availability(ConfigureAvailabilityRequest $request, Activity $activity, ActivityService $service): JsonResponse
    {
        $this->authorize('update', $activity);
        abort_unless($service->activity_id === $activity->id, 404);
        DB::transaction(function () use ($request, $service): void {
            foreach ($request->validated('rules', []) as $rule) {
                $service->availabilityRules()->updateOrCreate([
                    'weekday' => $rule['weekday'], 'start_time' => $rule['start_time'],
                    'effective_from' => $rule['effective_from'] ?? null, 'effective_to' => $rule['effective_to'] ?? null,
                ], ['capacity' => $rule['capacity'], 'active' => $rule['active'] ?? true]);
            }
            foreach ($request->validated('exceptions', []) as $exception) {
                $service->availabilityExceptions()->updateOrCreate(['date' => $exception['date']], ['is_closed' => $exception['is_closed'], 'slots' => $exception['slots'] ?? null, 'note' => $exception['note'] ?? null]);
            }
        });

        return response()->json(['data' => ActivityServiceResource::make($service->load(['availabilityRules', 'availabilityExceptions']))]);
    }

    private function serviceData(UpsertActivityServiceRequest $request, Activity $activity): array
    {
        $data = $request->validated();
        $menuUlid = $data['preorder_menu_ulid'] ?? null;
        unset($data['preorder_menu_ulid']);
        $data['preorder_menu_id'] = $menuUlid ? Menu::query()->where('activity_id', $activity->id)->where('ulid', $menuUlid)->firstOrFail()->id : null;

        return $data;
    }
}
