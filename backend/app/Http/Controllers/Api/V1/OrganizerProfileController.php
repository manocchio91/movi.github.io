<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizerProfileRequest;
use App\Models\OrganizerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class OrganizerProfileController extends Controller
{
    public function store(StoreOrganizerProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user->organizerProfile()->exists(), 409, 'Profilo organizzatore già presente.');

        $profile = OrganizerProfile::query()->create([
            ...$request->validated(),
            'ulid' => (string) Str::ulid(),
            'owner_user_id' => $user->id,
            'verification_status' => 'pending',
        ]);

        return response()->json(['data' => $profile], 201);
    }

    public function show(): JsonResponse
    {
        return response()->json(['data' => request()->user()->organizerProfile?->load('municipality')]);
    }

    public function update(StoreOrganizerProfileRequest $request): JsonResponse
    {
        $profile = $request->user()->organizerProfile;
        abort_unless($profile !== null, 404, 'Profilo organizzatore non trovato.');

        $profile->update($request->validated());

        return response()->json(['data' => $profile->refresh()->load('municipality')]);
    }
}
