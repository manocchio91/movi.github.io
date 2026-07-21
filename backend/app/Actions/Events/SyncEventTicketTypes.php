<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SyncEventTicketTypes
{
    /** @param array<int, array<string, mixed>> $ticketTypes */
    public function handle(Event $event, array $ticketTypes): void
    {
        $existing = $event->ticketTypes()->lockForUpdate()->get()->keyBy('ulid');
        $retainedUlids = [];

        foreach ($ticketTypes as $index => $data) {
            $ulid = $data['ulid'] ?? null;

            if ($ulid !== null) {
                /** @var TicketType|null $ticket */
                $ticket = $existing->get($ulid);
                if ($ticket === null) {
                    throw ValidationException::withMessages([
                        "ticket_types.$index.ulid" => ['Biglietto non appartenente a questo evento.'],
                    ]);
                }

                $maxQuantity = (int) $data['max_quantity'];
                if ($maxQuantity < $ticket->booked_quantity) {
                    throw ValidationException::withMessages([
                        "ticket_types.$index.max_quantity" => ["La quantità non può essere inferiore alle {$ticket->booked_quantity} prenotazioni già registrate."],
                    ]);
                }

                $ticket->update($this->mutableData($data));
                $retainedUlids[] = $ulid;

                continue;
            }

            $event->ticketTypes()->create([
                ...$this->mutableData($data),
                'ulid' => (string) Str::ulid(),
                'booked_quantity' => 0,
            ]);
        }

        foreach ($existing as $ulid => $ticket) {
            if (in_array($ulid, $retainedUlids, true)) {
                continue;
            }

            if ($ticket->booked_quantity > 0) {
                throw ValidationException::withMessages([
                    'ticket_types' => ["Il biglietto {$ticket->name} non può essere rimosso perché ha prenotazioni."],
                ]);
            }

            $ticket->delete();
        }
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function mutableData(array $data): array
    {
        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'audience' => $data['audience'],
            'max_quantity' => $data['max_quantity'],
            'price_cents' => $data['price_cents'],
            'is_active' => $data['is_active'] ?? true,
        ];
    }
}
