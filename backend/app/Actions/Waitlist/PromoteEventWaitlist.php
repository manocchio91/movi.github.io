<?php

namespace App\Actions\Waitlist;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\TicketType;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PromoteEventWaitlist
{
    public function handle(int $eventId): ?WaitlistEntry
    {
        return DB::transaction(function () use ($eventId): ?WaitlistEntry {
            $event = Event::query()->whereKey($eventId)->lockForUpdate()->first();
            if ($event === null || $event->starts_at->isPast()) {
                return null;
            }

            $activeOffer = WaitlistEntry::query()->where('event_id', $eventId)->where('status', 'offered')
                ->where('reserved_until', '>', now())->lockForUpdate()->first();
            if ($activeOffer !== null) {
                return $activeOffer;
            }

            $candidates = WaitlistEntry::query()->where('event_id', $eventId)->where('status', 'waiting')
                ->orderBy('created_at')->orderBy('id')->lockForUpdate()->get();
            $entry = $candidates->first(fn (WaitlistEntry $candidate): bool => $this->compatible($candidate, $event));
            if ($entry === null) {
                return null;
            }

            $event->decrement('available_total', $entry->requested_places);
            $event->refresh();
            if ($event->available_total === 0) {
                $event->forceFill(['status' => EventStatus::SoldOut])->save();
            }

            $entry->forceFill([
                'status' => 'offered', 'offered_at' => now(),
                'reserved_until' => now()->addMinutes((int) config('waitlist.offer_minutes', 15)),
            ])->save();

            $notification = Notification::query()->firstOrCreate(
                ['deduplication_key' => "waitlist:{$entry->id}:offered:internal"],
                [
                    'ulid' => (string) Str::ulid(), 'user_id' => $entry->user_id, 'event_id' => $event->id,
                    'kind' => 'waitlist_offer', 'channel' => 'internal', 'template' => 'waitlist_offer',
                    'title' => 'Posto disponibile', 'message' => "Hai un'offerta temporanea per {$event->title}.",
                    'status' => 'sent', 'read' => false,
                    'payload' => ['waitlist_ulid' => $entry->ulid, 'reserved_until' => $entry->reserved_until?->toIso8601String()],
                    'sent_at' => now(),
                ],
            );
            NotificationDelivery::query()->firstOrCreate(
                ['notification_id' => $notification->id, 'channel' => 'internal'],
                ['status' => 'sent', 'recipient' => (string) $entry->user_id, 'sent_at' => now()],
            );

            return $entry->refresh();
        });
    }

    private function compatible(WaitlistEntry $entry, Event $event): bool
    {
        if ($entry->requested_places > $event->available_total) {
            return false;
        }
        foreach (($entry->booking_payload['items'] ?? []) as $item) {
            $ticket = TicketType::query()->where('event_id', $event->id)->where('ulid', $item['ticket_type_ulid'])->first();
            if ($ticket === null || ! $ticket->is_active) {
                return false;
            }
            if ($ticket->max_quantity !== null && $ticket->booked_quantity + (int) $item['quantity'] > $ticket->max_quantity) {
                return false;
            }
        }

        return true;
    }
}
