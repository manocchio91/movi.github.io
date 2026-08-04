<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminPlanRequest;
use App\Http\Requests\AssignCommercialPlanRequest;
use App\Models\OrganizerProfile;
use App\Models\OrganizerSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class AdminCommercialController extends Controller
{
    public function plans(): JsonResponse
    {
        return response()->json(['data' => SubscriptionPlan::query()->orderBy('name')->get()]);
    }

    public function storePlan(AdminPlanRequest $request): JsonResponse
    {
        $data = $request->validated();
        $plan = SubscriptionPlan::query()->create([
            ...$data,
            'ulid' => (string) Str::ulid(),
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)),
            'is_public' => true,
        ]);

        return response()->json(['data' => $plan], 201);
    }

    public function updatePlan(AdminPlanRequest $request, SubscriptionPlan $plan): JsonResponse
    {
        $plan->update($request->validated());

        return response()->json(['data' => $plan->refresh()]);
    }

    public function assign(AssignCommercialPlanRequest $request, OrganizerProfile $organizer): JsonResponse
    {
        $data = $request->validated();
        $plan = SubscriptionPlan::query()->where('ulid', $data['plan_ulid'])->firstOrFail();
        unset($data['plan_ulid']);

        $subscription = OrganizerSubscription::query()->updateOrCreate(
            ['organizer_profile_id' => $organizer->id],
            ['ulid' => (string) Str::ulid(), 'subscription_plan_id' => $plan->id, ...$data],
        );

        return response()->json(['data' => $subscription->load('plan')]);
    }
}
