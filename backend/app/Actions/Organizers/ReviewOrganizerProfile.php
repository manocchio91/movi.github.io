<?php

namespace App\Actions\Organizers;

use App\Models\OrganizerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReviewOrganizerProfile
{
    public function execute(OrganizerProfile $profile, User $admin, string $decision, ?string $reason = null): OrganizerProfile
    {
        return DB::transaction(function () use ($profile, $admin, $decision, $reason): OrganizerProfile {
            $profile = OrganizerProfile::query()->lockForUpdate()->findOrFail($profile->id);
            abort_unless($profile->verification_status === 'pending', 409, 'La richiesta non è più in revisione.');

            $status = match ($decision) {
                'approve' => 'verified',
                'request_changes' => 'changes_requested',
                'reject' => 'rejected',
                default => abort(422, 'Decisione non valida.'),
            };

            abort_if($status !== 'verified' && blank($reason), 422, 'La motivazione è obbligatoria.');

            $profile->forceFill([
                'verification_status' => $status,
                'verified_at' => $status === 'verified' ? now() : null,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_notes' => $status === 'verified' ? null : trim((string) $reason),
            ])->save();

            return $profile->refresh();
        });
    }
}
