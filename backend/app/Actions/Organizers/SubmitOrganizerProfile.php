<?php

namespace App\Actions\Organizers;

use App\Models\OrganizerProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SubmitOrganizerProfile
{
    public function execute(OrganizerProfile $profile): OrganizerProfile
    {
        return DB::transaction(function () use ($profile): OrganizerProfile {
            $profile = OrganizerProfile::query()->lockForUpdate()->findOrFail($profile->id);

            abort_unless(in_array($profile->verification_status, ['incomplete', 'changes_requested'], true), 409, 'La richiesta non può essere inviata nello stato corrente.');

            Validator::make($profile->getAttributes(), [
                'name' => ['required', 'string', 'max:180'],
                'address' => ['required', 'string', 'max:255'],
                'street_number' => ['required', 'string', 'max:20'],
                'postal_code' => ['required', 'regex:/^\d{5}$/'],
                'municipality_id' => ['required', 'integer', 'exists:municipalities,id'],
                'province' => ['required', 'regex:/^[A-Z]{2}$/'],
                'country' => ['required', 'string', 'max:80'],
                'vat_number' => ['required', 'regex:/^(IT)?\d{11}$/'],
                'pec' => ['required', 'email:rfc', 'max:255'],
                'sdi_code' => ['required', 'alpha_num:ascii', 'size:7'],
                'verification_status' => [Rule::in(['incomplete', 'changes_requested'])],
            ])->validate();

            $profile->forceFill([
                'verification_status' => 'pending',
                'submitted_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_notes' => null,
            ])->save();

            return $profile->refresh();
        });
    }
}
