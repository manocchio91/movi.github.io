<?php

namespace App\Actions\Events;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\OrganizerProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateEvent
{
    public function handle(OrganizerProfile $profile, array $data): Event
    {
        return DB::transaction(function () use ($profile, $data): Event {
            $categoryIds = $data['category_ids'];
            unset($data['category_ids']);

            $event = $profile->events()->create([
                ...$data,
                'ulid' => (string) Str::ulid(),
                'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(6)),
                'status' => EventStatus::Draft,
                'available_total' => $data['capacity_total'],
            ]);

            $event->categories()->sync($categoryIds);

            return $event;
        });
    }

    public function update(Event $event, array $data): Event
    {
        return DB::transaction(function () use ($event, $data): Event {
            $categoryIds = $data['category_ids'];
            unset($data['category_ids']);

            $event->update([
                ...$data,
                'available_total' => $data['capacity_total'],
            ]);
            $event->categories()->sync($categoryIds);

            return $event->refresh();
        });
    }
}
