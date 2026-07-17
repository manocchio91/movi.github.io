<?php

namespace App\Actions\Events;

use App\Enums\EventStatus;
use App\Jobs\SendEventCancellationEmails;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventCancellation;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CancelEvent
{
    public function handle(Event $event, User $actor, array $data, bool $adminAction = false): Event
    {
        return DB::transaction(function () use ($event, $actor, $data, $adminAction): Event {
            /** @var Event $locked */
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            abort_unless($adminAction || $locked->organizerProfile?->owner_user_id === $actor->id, 403);
            abort_if(in_array($locked->status, [EventStatus::Cancelled, EventStatus::Completed, EventStatus::Archived], true), 422, 'Evento non annullabile.');
            abort_if(($locked->ends_at ?? $locked->starts_at)->isPast(), 422, 'Evento gia concluso.');

            $activeBookings = $locked->bookings()
                ->whereIn('status', ['confirmed', 'checked_in'])
                ->lockForUpdate()
                ->get();
            $peopleAffected = (int) $activeBookings->sum('total_people');

            $locked->forceFill([
                'status' => EventStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor->id,
                'cancellation_reason' => $data['reason_code'],
                'cancellation_notes' => $data['reason'],
                'booking_closes_at' => now(),
            ])->save();

            foreach ($activeBookings as $booking) {
                $booking->forceFill([
                    'status' => 'cancelled_by_organizer',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $data['reason'],
                    'cancelled_by_user_id' => $actor->id,
                ])->save();

                $deduplicationKey = "event-cancelled:{$locked->id}:booking:{$booking->id}";
                $notification = Notification::query()->firstOrCreate(
                    ['deduplication_key' => $deduplicationKey],
                    [
                        'ulid' => (string) Str::ulid(),
                        'user_id' => $booking->user_id,
                        'event_id' => $locked->id,
                        'kind' => 'event_cancelled',
                        'channel' => 'internal',
                        'template' => 'event_cancelled_operational',
                        'title' => 'Evento annullato',
                        'message' => "L'evento \"{$locked->title}\" e stato annullato. Motivo: {$data['reason']}",
                        'status' => 'queued',
                        'read' => false,
                        'payload' => [
                            'booking_ulid' => $booking->ulid,
                            'event_title' => $locked->title,
                            'organizer' => $locked->organizerProfile?->name,
                            'starts_at' => $locked->starts_at,
                            'reason_code' => $data['reason_code'],
                            'reason' => $data['reason'],
                        ],
                    ],
                );

                $notification->deliveries()->firstOrCreate(
                    ['channel' => 'email'],
                    [
                        'status' => 'queued',
                        'recipient' => $booking->user->email,
                    ],
                );
            }

            EventCancellation::query()->create([
                'event_id' => $locked->id,
                'cancelled_by_user_id' => $actor->id,
                'reason_code' => $data['reason_code'],
                'reason_text' => $data['reason'],
                'bookings_affected' => $activeBookings->count(),
                'people_affected' => $peopleAffected,
                'cancelled_at' => now(),
            ]);

            AuditLog::query()->create([
                'actor_user_id' => $actor->id,
                'action' => $adminAction ? 'admin.event.suspended' : 'organizer.event.cancelled',
                'subject_type' => Event::class,
                'subject_id' => $locked->id,
                'metadata' => [
                    'reason_code' => $data['reason_code'],
                    'reason' => $data['reason'],
                    'bookings_affected' => $activeBookings->count(),
                    'people_affected' => $peopleAffected,
                ],
                'request_id' => request()->attributes->get('request_id'),
            ]);

            DB::afterCommit(fn () => SendEventCancellationEmails::dispatch($locked->id));

            return $locked->refresh();
        });
    }
}
