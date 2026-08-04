<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Organizers\SubmitOrganizerProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrganizerProfileRequest;
use App\Http\Requests\UploadOrganizerImagesRequest;
use App\Models\OrganizerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Throwable;

class OrganizerProfileController extends Controller
{
    public function show(): JsonResponse
    {
        $profile = request()->user()->organizerProfile()->first();
        abort_unless($profile !== null, 404, 'Profilo organizzatore non trovato.');

        return response()->json(['data' => $this->withImageUrls($profile->load('municipality'))]);
    }

    public function update(UpdateOrganizerProfileRequest $request): JsonResponse
    {
        $profile = $request->user()->organizerProfile()->first();
        abort_unless($profile !== null, 404, 'Profilo organizzatore non trovato.');
        $this->authorize('update', $profile);
        $fiscalFields = ['name', 'type', 'municipality_id', 'address', 'street_number', 'postal_code', 'province', 'country', 'vat_number', 'pec', 'sdi_code'];
        abort_if(
            ! in_array($profile->verification_status, ['incomplete', 'changes_requested'], true)
            && array_intersect(array_keys($request->validated()), $fiscalFields) !== [],
            409,
            'I dati fiscali non sono modificabili nello stato corrente.',
        );
        $profile->update($request->validated());

        return response()->json(['data' => $this->withImageUrls($profile->refresh()->load('municipality'))]);
    }

    public function images(UploadOrganizerImagesRequest $request): JsonResponse
    {
        $profile = $request->user()->organizerProfile()->firstOrFail();
        $stored = [];
        $oldPaths = [];

        try {
            foreach (['logo', 'cover'] as $field) {
                if (! $request->hasFile($field)) {
                    continue;
                }
                $column = $field.'_path';
                $oldPaths[] = $profile->{$column};
                $stored[] = $profile->{$column} = $request->file($field)->store('organizers/'.$profile->ulid, 'public');
            }
            $profile->save();
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($stored);
            throw $exception;
        }
        Storage::disk('public')->delete(array_filter($oldPaths));

        return response()->json(['data' => $this->withImageUrls($profile->refresh())]);
    }

    public function submit(SubmitOrganizerProfile $submitOrganizerProfile): JsonResponse
    {
        $profile = request()->user()->organizerProfile()->first();
        abort_unless($profile !== null, 404, 'Profilo organizzatore non trovato.');
        $this->authorize('update', $profile);

        return response()->json(['data' => $submitOrganizerProfile->execute($profile)->load('municipality')]);
    }

    private function withImageUrls(OrganizerProfile $profile): OrganizerProfile
    {
        $profile->setAttribute('logo_url', $profile->logo_path ? Storage::disk('public')->url($profile->logo_path) : null);
        $profile->setAttribute('cover_url', $profile->cover_path ? Storage::disk('public')->url($profile->cover_path) : null);

        return $profile;
    }
}
