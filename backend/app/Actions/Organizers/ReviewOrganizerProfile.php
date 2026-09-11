<?php

namespace App\Actions\Organizers;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\OrganizerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

            if ($status === 'verified') {
                $activity = $profile->activities()->oldest('id')->first() ?? new Activity([
                    'ulid' => (string) Str::ulid(),
                    'organizer_profile_id' => $profile->id,
                    'slug' => Str::slug($profile->name).'-'.strtolower(Str::random(6)),
                    'booking_enabled' => false,
                ]);
                $activity->fill([
                    'municipality_id' => $profile->municipality_id,
                    'name' => $profile->name,
                    'type' => str_contains(strtolower((string) $profile->type), 'ristor') ? ActivityType::Restaurant : ActivityType::Other,
                    'description' => $profile->description,
                    'address' => trim(implode(' ', array_filter([$profile->address, $profile->street_number]))),
                    'postal_code' => $profile->postal_code,
                    'phone' => $profile->phone,
                    'public_email' => $profile->public_email,
                    'status' => ActivityStatus::Published,
                ]);
                $activity->save();
            }

            return $profile->refresh();
        });
    }
}
