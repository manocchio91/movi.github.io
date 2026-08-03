<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Organizers\SubmitOrganizerProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrganizerProfileRequest;
use Illuminate\Http\JsonResponse;

class OrganizerProfileController extends Controller
{
    public function show(): JsonResponse
    {
        $profile = request()->user()->organizerProfile()->first();
        abort_unless($profile !== null, 404, 'Profilo organizzatore non trovato.');

        return response()->json(['data' => $profile->load('municipality')]);
    }

    public function update(UpdateOrganizerProfileRequest $request): JsonResponse
    {
        $profile = $request->user()->organizerProfile()->first();
        abort_unless($profile !== null, 404, 'Profilo organizzatore non trovato.');
        $this->authorize('update', $profile);
        abort_unless(in_array($profile->verification_status, ['incomplete', 'changes_requested'], true), 409, 'Il profilo non è modificabile nello stato corrente.');

        $profile->update($request->validated());

        return response()->json(['data' => $profile->refresh()->load('municipality')]);
    }

    public function submit(SubmitOrganizerProfile $submitOrganizerProfile): JsonResponse
    {
        $profile = request()->user()->organizerProfile()->first();
        abort_unless($profile !== null, 404, 'Profilo organizzatore non trovato.');
        $this->authorize('update', $profile);

        return response()->json(['data' => $submitOrganizerProfile->execute($profile)->load('municipality')]);
    }
}
