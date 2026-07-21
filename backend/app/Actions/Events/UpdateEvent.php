<?php

namespace App\Actions\Events;

use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateEvent
{
    public function __construct(private readonly SyncEventTicketTypes $syncTicketTypes) {}

    /** @param array<string, mixed> $data */
    public function handle(Event $event, array $data): Event
    {
        return DB::transaction(function () use ($event, $data): Event {
            $lockedEvent = Event::query()->lockForUpdate()->findOrFail($event->id);
            $categoryIds = $data['category_ids'];
            $hasTicketTypes = array_key_exists('ticket_types', $data);
            $ticketTypes = $data['ticket_types'] ?? [];
            unset($data['category_ids'], $data['ticket_types']);

            $bookedPlaces = $lockedEvent->capacity_total - $lockedEvent->available_total;
            if ($data['capacity_total'] < $bookedPlaces) {
                throw ValidationException::withMessages([
                    'capacity_total' => ["La capienza non può essere inferiore ai $bookedPlaces posti già prenotati."],
                ]);
            }

            $lockedEvent->update([
                ...$data,
                'available_total' => $data['capacity_total'] - $bookedPlaces,
            ]);
            $lockedEvent->categories()->sync($categoryIds);

            if ($hasTicketTypes) {
                $this->syncTicketTypes->handle($lockedEvent, $ticketTypes);
            }

            return $lockedEvent->refresh()->load(['categories', 'ticketTypes', 'municipality']);
        });
    }
}
