<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrganizerProfile;
use Illuminate\Http\JsonResponse;

class OrganizerModerationController extends Controller
{
    public function pending(): JsonResponse
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        return response()->json([
            'data' => OrganizerProfile::query()->where('verification_status', 'pending')->with(['owner', 'municipality'])->get(),
        ]);
    }

    public function approve(OrganizerProfile $organizer): JsonResponse
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        $organizer->forceFill([
            'verification_status' => 'verified',
            'verified_at' => now(),
        ])->save();

        return response()->json(['data' => $organizer->refresh()]);
    }

    public function reject(OrganizerProfile $organizer): JsonResponse
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        $organizer->forceFill([
            'verification_status' => 'rejected',
            'verified_at' => null,
        ])->save();

        return response()->json(['data' => $organizer->refresh()]);
    }
}
