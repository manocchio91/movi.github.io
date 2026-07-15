<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Preferences\UpdateCustomerPreferences;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePreferencesRequest;
use Illuminate\Http\JsonResponse;

class PreferenceController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => request()->user()->preferredCategories()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdatePreferencesRequest $request, UpdateCustomerPreferences $preferences): JsonResponse
    {
        $preferences->handle($request->user(), $request->validated('category_ids'));

        return response()->json([
            'data' => $request->user()->preferredCategories()->orderBy('name')->get(),
        ]);
    }
}
