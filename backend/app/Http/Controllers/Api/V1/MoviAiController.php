<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MoviAiPlanRequest;
use App\Services\MoviAi\MoviAiOrchestrator;
use Illuminate\Http\JsonResponse;

final class MoviAiController extends Controller
{
    public function plan(MoviAiPlanRequest $request, MoviAiOrchestrator $orchestrator): JsonResponse
    {
        $data = $request->validated();

        return response()->json(['data' => $orchestrator->handle($data['message'], $data['previous_intent'] ?? null, $request->user())]);
    }
}
