<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationPreferencesRequest;
use Illuminate\Http\JsonResponse;

class NotificationPreferenceController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => request()->user()->notificationPreference()->firstOrCreate([], [
                'internal_enabled' => true,
                'email_enabled' => true,
                'promotional_enabled' => false,
                'frequency' => 'immediate',
            ]),
        ]);
    }

    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $preference = $request->user()->notificationPreference()->updateOrCreate([], $request->validated());

        return response()->json(['data' => $preference]);
    }
}
