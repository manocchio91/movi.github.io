<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\OrganizerOpportunityEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizerOpportunityController extends Controller
{
    public function index(Request $request, OrganizerOpportunityEngine $engine): JsonResponse
    {
        $profile = $request->user()->organizerProfile;
        abort_unless($profile, 403);

        $input = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'municipality_id' => ['nullable', 'exists:municipalities,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        return response()->json(['data' => $engine->analyze($profile, $input)]);
    }
}
