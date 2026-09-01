<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneratePlanRequest;
use App\Http\Requests\PlanInteractionRequest;
use App\Http\Requests\RevalidatePlanRequest;
use App\Services\Analytics\AnalyticsRecorder;
use App\Services\Planning\PlanningEngine;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    public function interaction(PlanInteractionRequest $request, AnalyticsRecorder $analytics): JsonResponse
    {
        $data = $request->validated();
        $analytics->record($data['action'], user: $request->user(), deduplicationKey: $data['action'].':'.$data['plan_id'].':'.(auth()->id() ?? 'guest'), metadata: array_filter(['entity_type' => $data['entity_type'] ?? null, 'entity_id' => $data['entity_id'] ?? null]));

        return response()->json(['data' => ['recorded' => true]]);
    }

    public function generate(GeneratePlanRequest $request, PlanningEngine $engine, AnalyticsRecorder $analytics): JsonResponse
    {
        $analytics->record('plan_requested', user: $request->user(), deduplicationKey: 'plan_requested:'.(auth()->id() ?? 'guest').':'.now()->format('YmdHi'));
        $plans = $engine->generate($request->validated(), $request->user());
        $analytics->record('plan_generated', user: $request->user(), deduplicationKey: 'plan_generated:'.(auth()->id() ?? 'guest').':'.now()->format('YmdHi'), metadata: ['plans_count' => count($plans)]);

        return response()->json(['data' => $plans]);
    }

    public function revalidate(RevalidatePlanRequest $request, PlanningEngine $engine, AnalyticsRecorder $analytics): JsonResponse
    {
        $result = $engine->revalidate($request->validated('steps'));
        $analytics->record('plan_revalidated', user: $request->user(), metadata: ['available' => $result['available']]);
        if (! $result['available']) {
            $analytics->record('plan_unavailable_on_revalidation', user: $request->user(), metadata: ['unavailable_count' => count($result['unavailable_steps'])]);
        }

        return response()->json(['data' => $result]);
    }
}
