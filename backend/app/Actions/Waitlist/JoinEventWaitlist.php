<?php

namespace App\Actions\Waitlist;

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JoinEventWaitlist
{
    public function handle(User $user, Event $event, array $data): WaitlistEntry
    {
        return DB::transaction(function () use ($user, $event, $data): WaitlistEntry {
            $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedEvent->starts_at->isPast(), 422, 'Evento concluso.');

            $requested = (int) $data['adults_count'] + (int) ($data['children_count'] ?? 0);
            abort_if($requested < 1, 422, 'Seleziona almeno un partecipante.');
            abort_if(collect($data['items'])->sum('quantity') !== $requested, 422, 'I biglietti devono corrispondere ai partecipanti.');
            abort_if($lockedEvent->available_total >= $requested, 422, 'I posti richiesti sono ancora prenotabili.');

            foreach ($data['items'] as $item) {
                $ticket = TicketType::query()->where('event_id', $event->id)->where('ulid', $item['ticket_type_ulid'])->firstOrFail();
                abort_unless($ticket->is_active, 422, 'Tipologia biglietto non attiva.');
            }

            $entry = WaitlistEntry::query()->where('event_id', $event->id)->where('user_id', $user->id)->lockForUpdate()->first();
            if ($entry !== null && in_array($entry->status, ['waiting', 'offered'], true)) {
                return $entry->load(['event', 'booking']);
            }

            $values = [
                'ulid' => (string) Str::ulid(), 'requested_places' => $requested,
                'booking_payload' => [
                    'adults_count' => (int) $data['adults_count'],
                    'children_count' => (int) ($data['children_count'] ?? 0),
                    'items' => $data['items'],
                ],
                'status' => 'waiting', 'reserved_until' => null, 'offered_at' => null,
                'accepted_at' => null, 'expired_at' => null, 'cancelled_at' => null, 'booking_id' => null,
            ];

            if ($entry === null) {
                $entry = WaitlistEntry::query()->create([...$values, 'event_id' => $event->id, 'user_id' => $user->id]);
            } else {
                $entry->forceFill($values)->save();
            }

            return $entry->load(['event', 'booking']);
        });
    }
}
