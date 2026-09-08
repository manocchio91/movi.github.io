<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Organizers\SubmitOrganizerProfile;
use App\Actions\Organizers\SubmitOrganizerProfileChanges;
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

    public function update(UpdateOrganizerProfileRequest $request, SubmitOrganizerProfileChanges $submitChanges): JsonResponse
    {
        $profile = $request->user()->organizerProfile()->first();
        abort_unless($profile !== null, 404, 'Profilo organizzatore non trovato.');
        $this->authorize('update', $profile);
        if ($profile->verification_status === 'verified') {
            $profile = $submitChanges->execute($profile, $request->validated());
        } else {
            abort_unless(in_array($profile->verification_status, ['incomplete', 'changes_requested'], true), 409, 'Il profilo non è modificabile nello stato corrente.');
            $profile->update($request->validated());
        }

        return response()->json(['data' => $this->withImageUrls($profile->refresh()->load('municipality'))]);
    }

    public function images(UploadOrganizerImagesRequest $request): JsonResponse
    {
        $profile = $request->user()->organizerProfile()->firstOrFail();
        $stored = [];
        $oldPaths = [];
        $changes = [];

        try {
            foreach (['logo', 'cover'] as $field) {
                if (! $request->hasFile($field)) {
                    continue;
                }
                $column = $field.'_path';
                $path = $request->file($field)->store('organizers/'.$profile->ulid, (string) config('filesystems.public', 'public'));
                $stored[] = $path;
                if ($profile->verification_status === 'verified') {
                    $pending = $profile->profile_change_status === 'pending' ? ($profile->pending_changes ?? []) : [];
                    if (isset($pending[$column])) {
                        $oldPaths[] = $pending[$column];
                    }
                    $changes[$column] = $path;
                } else {
                    $oldPaths[] = $profile->{$column};
                    $profile->{$column} = $path;
                }
            }
            if ($profile->verification_status === 'verified') {
                $profile = app(SubmitOrganizerProfileChanges::class)->execute($profile, $changes);
            } else {
                $profile->save();
            }
        } catch (Throwable $exception) {
            Storage::disk((string) config('filesystems.public', 'public'))->delete($stored);
            throw $exception;
        }
        Storage::disk((string) config('filesystems.public', 'public'))->delete(array_filter($oldPaths));

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
        $profile->makeVisible('pending_changes');
        $profile->setAttribute('logo_url', $profile->logo_path ? Storage::disk((string) config('filesystems.public', 'public'))->url($profile->logo_path) : null);
        $profile->setAttribute('cover_url', $profile->cover_path ? Storage::disk((string) config('filesystems.public', 'public'))->url($profile->cover_path) : null);

        return $profile;
    }
}
