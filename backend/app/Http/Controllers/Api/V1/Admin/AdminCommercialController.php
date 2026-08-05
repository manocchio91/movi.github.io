<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminPlanRequest;
use App\Http\Requests\AssignCommercialPlanRequest;
use App\Models\OrganizerProfile;
use App\Models\OrganizerSubscription;
use App\Models\OrganizerSubscriptionHistory;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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

        if ($plan->billing_model === 'pay_per_booking') {
            abort_if(! array_key_exists('booking_fee_cents', $data), 422, 'Il costo personalizzato per partecipante è obbligatorio.');
        }
        if ($plan->billing_model === 'unlimited') {
            $data['booking_fee_cents'] = null;
        }
        $subscription = DB::transaction(function () use ($organizer, $plan, $data, $request): OrganizerSubscription {
            $subscription = OrganizerSubscription::query()->where('organizer_profile_id', $organizer->id)->lockForUpdate()->first();
            $previous = $subscription?->only(['subscription_plan_id', 'status', 'booking_fee_cents', 'agreement_starts_at', 'agreement_ends_at', 'agreement_notes']);
            $subscription ??= new OrganizerSubscription(['ulid' => (string) Str::ulid(), 'organizer_profile_id' => $organizer->id]);
            $subscription->fill(['subscription_plan_id' => $plan->id, ...$data])->save();
            OrganizerSubscriptionHistory::query()->create(['ulid' => (string) Str::ulid(), 'organizer_subscription_id' => $subscription->id, 'changed_by_user_id' => $request->user()->id, 'previous_values' => $previous, 'new_values' => $subscription->only(['subscription_plan_id', 'status', 'booking_fee_cents', 'agreement_starts_at', 'agreement_ends_at', 'agreement_notes']), 'created_at' => now()]);

            return $subscription;
        });

        return response()->json(['data' => $subscription->load(['plan', 'history'])]);
    }
}
