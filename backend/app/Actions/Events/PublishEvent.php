<?php

namespace App\Actions\Events;

use App\Enums\EventStatus;
use App\Jobs\NotifyInterestedCustomersJob;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishEvent
{
    public function handle(Event $event): Event
    {
        $published = DB::transaction(function () use ($event): Event {
            $lockedEvent = Event::query()
                ->with(['categories', 'ticketTypes', 'organizerProfile'])
                ->lockForUpdate()
                ->findOrFail($event->id);

            abort_unless($lockedEvent->organizerProfile?->verification_status === 'verified', 403, 'Profilo organizzatore non approvato.');

            if ($lockedEvent->status !== EventStatus::Draft) {
                throw ValidationException::withMessages(['status' => ['Evento non pubblicabile dallo stato corrente.']]);
            }

            $errors = [];
            if ($lockedEvent->starts_at->isPast()) {
                $errors['starts_at'][] = 'La data di inizio deve essere futura.';
            }
            if ($lockedEvent->categories->isEmpty()) {
                $errors['category_ids'][] = 'Seleziona almeno una categoria.';
            }
            if ($lockedEvent->ticketTypes->isEmpty()) {
                $errors['ticket_types'][] = 'Aggiungi almeno un biglietto.';
            }
            if (! $lockedEvent->ticketTypes->contains(fn ($ticket) => $ticket->is_active && $ticket->max_quantity > 0)) {
                $errors['ticket_types'][] = 'È necessario almeno un biglietto attivo con quantità disponibile.';
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $lockedEvent->forceFill([
                'status' => EventStatus::Published,
                'published_at' => now(),
            ])->save();

            return $lockedEvent->refresh()->load(['categories', 'ticketTypes', 'municipality']);
        });

        NotifyInterestedCustomersJob::dispatch($published->id)->afterCommit();

        return $published;
    }
}
