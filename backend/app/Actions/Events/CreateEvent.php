<?php

namespace App\Actions\Events;

use App\Actions\Notifications\NotifyInterestedCustomers;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\OrganizerProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateEvent
{
    public function handle(OrganizerProfile $profile, array $data): Event
    {
        abort_unless($profile->verification_status === 'verified', 403, 'Profilo organizzatore non approvato.');

        $event = DB::transaction(function () use ($profile, $data): Event {
            $categoryIds = $data['category_ids'];
            $ticketTypes = $data['ticket_types'] ?? [];
            unset($data['category_ids']);
            unset($data['ticket_types']);

            $event = $profile->events()->create([
                ...$data,
                'ulid' => (string) Str::ulid(),
                'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(6)),
                'status' => EventStatus::Published,
                'published_at' => now(),
                'available_total' => $data['capacity_total'],
            ]);

            $event->categories()->sync($categoryIds);
            $this->syncTicketTypes($event, $ticketTypes);

            return $event;
        });

        app(NotifyInterestedCustomers::class)->handle($event->refresh());

        return $event->refresh();
    }

    public function update(Event $event, array $data): Event
    {
        return DB::transaction(function () use ($event, $data): Event {
            $categoryIds = $data['category_ids'];
            $ticketTypes = $data['ticket_types'] ?? [];
            unset($data['category_ids']);
            unset($data['ticket_types']);

            $event->update([
                ...$data,
                'available_total' => $data['capacity_total'],
            ]);
            $event->categories()->sync($categoryIds);
            $this->syncTicketTypes($event, $ticketTypes);

            return $event->refresh();
        });
    }

    private function syncTicketTypes(Event $event, array $ticketTypes): void
    {
        if ($ticketTypes === []) {
            $event->ticketTypes()->updateOrCreate(
                ['name' => 'Ingresso'],
                [
                    'description' => 'Biglietto unico',
                    'audience' => 'generic',
                    'max_quantity' => $event->capacity_total,
                    'price_cents' => $event->is_free ? 0 : 1500,
                    'is_active' => true,
                ],
            );

            return;
        }

        $event->ticketTypes()->delete();

        foreach ($ticketTypes as $ticketType) {
            $event->ticketTypes()->create([
                'name' => $ticketType['name'],
                'description' => $ticketType['description'] ?? null,
                'audience' => $ticketType['audience'],
                'max_quantity' => $ticketType['max_quantity'] ?? null,
                'booked_quantity' => 0,
                'price_cents' => $ticketType['price_cents'],
                'is_active' => $ticketType['is_active'] ?? true,
            ]);
        }
    }
}
