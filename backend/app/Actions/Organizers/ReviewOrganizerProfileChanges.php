<?php

namespace App\Actions\Organizers;

use App\Models\Notification;
use App\Models\OrganizerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReviewOrganizerProfileChanges
{
    public function execute(OrganizerProfile $profile, User $admin, string $decision, ?string $reason = null): OrganizerProfile
    {
        return DB::transaction(function () use ($profile, $admin, $decision, $reason): OrganizerProfile {
            $profile = OrganizerProfile::query()->lockForUpdate()->findOrFail($profile->id);
            abort_unless($profile->profile_change_status === 'pending', 409, 'La richiesta non è più in revisione.');
            abort_unless(in_array($decision, ['approve', 'reject'], true), 422, 'Decisione non valida.');
            abort_if($decision === 'reject' && blank($reason), 422, 'La motivazione è obbligatoria.');

            $changes = $profile->pending_changes ?? [];
            $oldImages = [];
            if ($decision === 'approve') {
                foreach (['logo_path', 'cover_path'] as $field) {
                    if (array_key_exists($field, $changes) && $profile->{$field}) {
                        $oldImages[] = $profile->{$field};
                    }
                }
                $profile->forceFill($changes);
            } else {
                $oldImages = array_values(array_filter([$changes['logo_path'] ?? null, $changes['cover_path'] ?? null]));
            }
            $status = $decision === 'approve' ? 'approved' : 'rejected';
            $profile->forceFill(['profile_change_status' => $status, 'profile_change_reviewed_by' => $admin->id, 'profile_change_reviewed_at' => now(), 'profile_change_review_notes' => $decision === 'reject' ? trim((string) $reason) : null])->save();

            Notification::query()->firstOrCreate(
                ['deduplication_key' => "organizer-profile-change-decision:{$profile->profile_change_request_ulid}:{$status}"],
                ['ulid' => (string) Str::ulid(), 'user_id' => $profile->owner_user_id, 'kind' => 'organizer_profile_change_decision', 'channel' => 'internal', 'template' => 'organizer_profile_change_decision', 'title' => $status === 'approved' ? 'Modifiche profilo approvate' : 'Modifiche profilo non approvate', 'message' => $status === 'approved' ? 'Le modifiche al profilo sono ora pubbliche.' : trim((string) $reason), 'status' => 'sent', 'read' => false, 'payload' => ['organizer_ulid' => $profile->ulid, 'request_ulid' => $profile->profile_change_request_ulid, 'status' => $status], 'sent_at' => now()]
            );
            DB::afterCommit(fn () => Storage::disk((string) config('filesystems.public', 'public'))->delete($oldImages));

            return $profile->refresh();
        });
    }
}
