<?php

namespace App\Actions\Events;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\OrganizerProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateEvent
{
    public function __construct(private readonly SyncEventTicketTypes $syncTicketTypes) {}

    /** @param array<string, mixed> $data */
    public function handle(OrganizerProfile $profile, array $data): Event
    {
        abort_unless($profile->verification_status === 'verified', 403, 'Profilo organizzatore non approvato.');

        return DB::transaction(function () use ($profile, $data): Event {
            $categoryIds = $data['category_ids'];
            $ticketTypes = $data['ticket_types'] ?? [];
            unset($data['category_ids'], $data['ticket_types']);

            $event = $profile->events()->create([
                ...$data,
                'ulid' => (string) Str::ulid(),
                'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(6)),
                'status' => EventStatus::Draft,
                'published_at' => null,
                'available_total' => $data['capacity_total'],
            ]);

            $event->categories()->sync($categoryIds);
            if ($ticketTypes !== []) {
                $this->syncTicketTypes->handle($event, $ticketTypes);
            }

            return $event->refresh()->load(['categories', 'ticketTypes', 'municipality']);
        });
    }
}
