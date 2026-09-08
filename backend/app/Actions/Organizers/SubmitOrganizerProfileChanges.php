<?php

namespace App\Actions\Organizers;

use App\Enums\UserRole;
use App\Models\Notification;
use App\Models\OrganizerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubmitOrganizerProfileChanges
{
    public function execute(OrganizerProfile $profile, array $changes): OrganizerProfile
    {
        return DB::transaction(function () use ($profile, $changes): OrganizerProfile {
            $profile = OrganizerProfile::query()->lockForUpdate()->findOrFail($profile->id);
            $pending = $profile->profile_change_status === 'pending' ? ($profile->pending_changes ?? []) : [];
            foreach ($changes as $field => $value) {
                if ($profile->{$field} == $value) {
                    unset($pending[$field]);
                } else {
                    $pending[$field] = $value;
                }
            }
            abort_if($pending === [], 422, 'Nessuna modifica da inviare.');

            $requestUlid = $profile->profile_change_status === 'pending'
                ? $profile->profile_change_request_ulid
                : (string) Str::ulid();
            $profile->forceFill([
                'profile_change_status' => 'pending',
                'pending_changes' => $pending,
                'profile_change_request_ulid' => $requestUlid,
                'profile_change_submitted_at' => now(),
                'profile_change_reviewed_by' => null,
                'profile_change_reviewed_at' => null,
                'profile_change_review_notes' => null,
            ])->save();

            User::query()->where('role', UserRole::Admin->value)->each(function (User $admin) use ($profile, $requestUlid): void {
                Notification::query()->firstOrCreate(
                    ['deduplication_key' => "organizer-profile-change:{$requestUlid}:{$admin->id}"],
                    ['ulid' => (string) Str::ulid(), 'user_id' => $admin->id, 'kind' => 'organizer_profile_change', 'channel' => 'internal', 'template' => 'organizer_profile_change', 'title' => 'Modifica profilo Organizer', 'message' => $profile->name.' ha inviato modifiche al profilo.', 'status' => 'sent', 'read' => false, 'payload' => ['organizer_ulid' => $profile->ulid, 'request_ulid' => $requestUlid, 'status' => 'pending'], 'sent_at' => now()]
                );
            });

            return $profile->refresh();
        });
    }
}
