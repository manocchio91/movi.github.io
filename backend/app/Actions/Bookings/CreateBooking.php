<?php

namespace App\Actions\Bookings;

use App\Enums\EventStatus;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Analytics\AnalyticsRecorder;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateBooking
{
    public function handle(User $user, Event $event, array $data, ?string $idempotencyKey): Booking
    {
        try {
            return $this->transaction(function () use ($user, $event, $data, $idempotencyKey): Booking {
                if ($idempotencyKey) {
                    $existing = $this->idempotentBooking($user, $event, $idempotencyKey);

                    if ($existing) {
                        return $this->responseBooking($existing);
                    }
                }

                /** @var Event $lockedEvent */
                $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

                // A concurrent request may have committed while this request waited for the event lock.
                if ($idempotencyKey) {
                    $existing = $this->idempotentBooking($user, $event, $idempotencyKey);

                    if ($existing) {
                        return $this->responseBooking($existing);
                    }
                }

                abort_unless($lockedEvent->status === EventStatus::Published, 422, 'Evento non prenotabile.');
                abort_if($lockedEvent->booking_closes_at && now()->greaterThan($lockedEvent->booking_closes_at), 422, 'Prenotazioni chiuse.');

                $totalPeople = (int) $data['adults_count'] + (int) ($data['children_count'] ?? 0);
                abort_if($totalPeople < 1, 422, 'Seleziona almeno un partecipante.');
                abort_if($lockedEvent->available_total < $totalPeople, 422, 'Posti non disponibili.');

                $ticketItems = collect($data['items'] ?? []);
                abort_if($ticketItems->sum('quantity') !== $totalPeople, 422, 'La quantita dei biglietti deve corrispondere ai partecipanti.');
                $totalPrice = 0;
                $qrToken = Str::random(64);

                foreach ($ticketItems as $item) {
                    /** @var TicketType $ticket */
                    $ticket = TicketType::query()
                        ->where('event_id', $lockedEvent->id)
                        ->where('ulid', $item['ticket_type_ulid'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    abort_unless($ticket->is_active, 422, 'Tipologia biglietto non attiva.');

                    if ($ticket->max_quantity !== null) {
                        abort_if($ticket->booked_quantity + (int) $item['quantity'] > $ticket->max_quantity, 422, 'Biglietti non disponibili.');
                    }

                    $ticket->increment('booked_quantity', (int) $item['quantity']);
                    $totalPrice += $ticket->price_cents * (int) $item['quantity'];
                }

                $booking = Booking::query()->create([
                    'ulid' => (string) Str::ulid(),
                    'user_id' => $user->id,
                    'event_id' => $lockedEvent->id,
                    'status' => 'confirmed',
                    'total_people' => $totalPeople,
                    'adults_count' => (int) $data['adults_count'],
                    'children_count' => (int) ($data['children_count'] ?? 0),
                    'total_price_cents' => $totalPrice,
                    'children_age_ranges' => $data['children_age_ranges'] ?? null,
                    'dietary_needs' => $data['dietary_needs'] ?? null,
                    'accessibility_needs' => $data['accessibility_needs'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'booking_code' => 'EVT-'.Str::upper(Str::random(8)),
                    'contact_phone' => $data['phone'],
                    'qr_token_hash' => Hash::make($qrToken),
                    'qr_token_encrypted' => $qrToken,
                    'booked_at' => now(),
                    'idempotency_key' => $idempotencyKey,
                    'event_terms_accepted' => true,
                ]);

                foreach ($ticketItems as $item) {
                    $ticket = TicketType::query()->where('ulid', $item['ticket_type_ulid'])->firstOrFail();
                    $booking->items()->create([
                        'ticket_type_id' => $ticket->id,
                        'quantity' => (int) $item['quantity'],
                        'unit_price_cents' => $ticket->price_cents,
                        'total_price_cents' => $ticket->price_cents * (int) $item['quantity'],
                    ]);
                }

                $lockedEvent->decrement('available_total', $totalPeople);
                $lockedEvent->refresh();

                if ($lockedEvent->available_total === 0) {
                    $lockedEvent->forceFill(['status' => EventStatus::SoldOut])->save();
                }

                $notification = Notification::query()->create([
                    'ulid' => (string) Str::ulid(),
                    'user_id' => $user->id,
                    'event_id' => $lockedEvent->id,
                    'kind' => 'booking_confirmed',
                    'channel' => 'internal',
                    'template' => 'booking_confirmed',
                    'title' => 'Prenotazione confermata',
                    'message' => "Hai prenotato {$booking->total_people} posti per {$lockedEvent->title}.",
                    'status' => 'sent',
                    'read' => false,
                    'payload' => ['booking_ulid' => $booking->ulid, 'booking_code' => $booking->booking_code],
                    'deduplication_key' => "booking:{$booking->id}:confirmed:internal",
                    'sent_at' => now(),
                ]);

                NotificationDelivery::query()->create([
                    'notification_id' => $notification->id,
                    'status' => 'sent',
                    'channel' => 'internal',
                    'recipient' => (string) $user->id,
                    'sent_at' => now(),
                ]);

                if ((bool) ($data['save_phone_to_profile'] ?? false)) {
                    $user->forceFill(['phone' => $data['phone']])->save();
                }

                $booking->setAttribute('qr_token', $qrToken);

                app(AnalyticsRecorder::class)->record('booking_created', $lockedEvent, $user, "booking_created:{$booking->id}");

                return $this->responseBooking($booking);
            });
        } catch (QueryException $exception) {
            if ($idempotencyKey && (string) $exception->getCode() === '23505') {
                $existing = $this->idempotentBooking($user, $event, $idempotencyKey);

                if ($existing) {
                    return $this->responseBooking($existing);
                }
            }

            throw $exception;
        }
    }

    /** @param Closure(): Booking $callback */
    protected function transaction(Closure $callback): Booking
    {
        return DB::transaction($callback);
    }

    private function idempotentBooking(User $user, Event $event, string $idempotencyKey): ?Booking
    {
        return Booking::query()
            ->where('user_id', $user->id)
            ->where('event_id', $event->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    private function responseBooking(Booking $booking): Booking
    {
        $booking->setAttribute('qr_token', $booking->qr_token_encrypted);

        return $booking->load(['items.ticketType', 'event']);
    }
}
