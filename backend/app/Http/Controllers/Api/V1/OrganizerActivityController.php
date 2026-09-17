<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActivityStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\ReservationResource;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class OrganizerActivityController extends Controller
{
    public function index(): JsonResponse
    {
        return ActivityResource::collection(request()->user()->organizerProfile->activities()->with(['municipality', 'categories', 'services.preorderMenu', 'services.availabilityRules', 'services.availabilityExceptions'])->latest()->get())->response();
    }

    public function store(UpsertActivityRequest $request): JsonResponse
    {
        $data = $request->validated();
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);
        $activity = $request->user()->organizerProfile->activities()->create([...$data, 'ulid' => (string) Str::ulid(), 'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)), 'status' => $data['status'] ?? ActivityStatus::Draft]);
        $activity->categories()->sync($categoryIds);

        return response()->json(['data' => ActivityResource::make($activity->load(['municipality', 'categories', 'services']))], 201);
    }

    public function show(Activity $activity): JsonResponse
    {
        $this->authorize('update', $activity);

        return response()->json(['data' => ActivityResource::make($activity->load(['municipality', 'categories', 'services.preorderMenu', 'services.availabilityRules', 'services.availabilityExceptions']))]);
    }

    public function update(UpsertActivityRequest $request, Activity $activity): JsonResponse
    {
        $this->authorize('update', $activity);
        $data = $request->validated();
        $categoryIds = $data['category_ids'] ?? null;
        unset($data['category_ids']);
        $activity->update($data);
        if ($categoryIds !== null) {
            $activity->categories()->sync($categoryIds);
        }

        return response()->json(['data' => ActivityResource::make($activity->refresh()->load(['municipality', 'categories', 'services.preorderMenu', 'services.availabilityRules', 'services.availabilityExceptions']))]);
    }

    public function reservations(Activity $activity): JsonResponse
    {
        $this->authorize('viewReservations', $activity);

        return ReservationResource::collection($activity->reservations()->with(['user', 'activity', 'service'])->latest('starts_at')->paginate(50))->response();
    }
}
