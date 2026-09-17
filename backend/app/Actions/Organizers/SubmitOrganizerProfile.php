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
            ], [
                'name.required' => 'Inserisci il nome dell’attività.',
                'address.required' => 'Inserisci l’indirizzo.',
                'street_number.required' => 'Inserisci il numero civico.',
                'postal_code.required' => 'Inserisci il CAP.',
                'postal_code.regex' => 'Il CAP deve contenere esattamente 5 cifre.',
                'municipality_id.required' => 'Seleziona il Comune.',
                'municipality_id.exists' => 'Il Comune selezionato non esiste.',
                'province.required' => 'Seleziona un Comune per determinare la Provincia.',
                'province.regex' => 'La Provincia deve contenere una sigla di 2 lettere.',
                'country.required' => 'Inserisci il Paese.',
                'vat_number.required' => 'Inserisci la Partita IVA.',
                'vat_number.regex' => 'La Partita IVA deve contenere 11 cifre, con prefisso IT facoltativo.',
                'pec.required' => 'Inserisci la PEC.',
                'pec.email' => 'La PEC deve essere un indirizzo email valido.',
                'sdi_code.required' => 'Inserisci il Codice SDI.',
                'sdi_code.alpha_num' => 'Il Codice SDI deve contenere solo lettere e numeri.',
                'sdi_code.size' => 'Il Codice SDI deve contenere esattamente 7 caratteri.',
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
