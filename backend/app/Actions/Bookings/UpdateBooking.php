<?php

namespace App\Actions\Bookings;

use App\Enums\EventStatus;
use App\Models\Booking;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateBooking
{
    public function handle(User $user, Booking $booking, array $data): Booking
    {
        return DB::transaction(function () use ($user, $booking, $data): Booking {
            /** @var Booking $lockedBooking */
            $lockedBooking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedBooking->user_id === $user->id, 403);
            abort_unless($lockedBooking->status === 'confirmed', 422, 'Prenotazione non modificabile.');
            abort_if($lockedBooking->checkIns()->exists(), 422, 'Una prenotazione con check-in non può essere modificata.');

            /** @var Event $event */
            $event = Event::query()->whereKey($lockedBooking->event_id)->lockForUpdate()->firstOrFail();
            abort_unless($event->starts_at->isFuture(), 422, 'Prenotazione non modificabile per un evento già iniziato.');

            $newTotal = (int) $data['adults_count'] + (int) ($data['children_count'] ?? 0);
            abort_if($newTotal < 1, 422, 'Seleziona almeno un partecipante.');
            abort_if($newTotal > $event->available_total + $lockedBooking->total_people, 422, 'Posti non disponibili.');

            $requested = collect($data['items'])->keyBy('ticket_type_ulid');
            abort_if($requested->sum('quantity') !== $newTotal, 422, 'La quantità dei biglietti deve corrispondere ai partecipanti.');

            $existingItems = $lockedBooking->items()->with('ticketType')->get();
            $ticketUlids = $requested->keys()->merge($existingItems->pluck('ticketType.ulid'))->filter()->unique()->values();
            $tickets = TicketType::query()->where('event_id', $event->id)->whereIn('ulid', $ticketUlids)->lockForUpdate()->get()->keyBy('ulid');
            abort_if($tickets->count() !== $ticketUlids->count(), 422, 'Una tipologia di biglietto non appartiene a questo evento.');

            foreach ($existingItems as $item) {
                $ticket = $tickets->get($item->ticketType?->ulid);
                if ($ticket) {
                    $ticket->booked_quantity -= $item->quantity;
                }
            }

            $totalPrice = 0;
            foreach ($requested as $ulid => $item) {
                /** @var TicketType $ticket */
                $ticket = $tickets->get($ulid);
                abort_unless($ticket->is_active, 422, 'Tipologia biglietto non attiva.');
                $quantity = (int) $item['quantity'];
                abort_if($ticket->max_quantity !== null && $ticket->booked_quantity + $quantity > $ticket->max_quantity, 422, 'Biglietti non disponibili.');
                $ticket->booked_quantity += $quantity;
                $totalPrice += $ticket->price_cents * $quantity;
            }

            foreach ($tickets as $ticket) {
                $ticket->save();
            }
            $lockedBooking->items()->delete();
            foreach ($requested as $ulid => $item) {
                $ticket = $tickets->get($ulid);
                $lockedBooking->items()->create(['ticket_type_id' => $ticket->id, 'quantity' => (int) $item['quantity'], 'unit_price_cents' => $ticket->price_cents, 'total_price_cents' => $ticket->price_cents * (int) $item['quantity']]);
            }

            $availableTotal = $event->available_total + $lockedBooking->total_people - $newTotal;
            $event->forceFill([
                'available_total' => $availableTotal,
                'status' => $availableTotal === 0 ? EventStatus::SoldOut : EventStatus::Published,
            ])->save();
            $lockedBooking->forceFill(['total_people' => $newTotal, 'adults_count' => (int) $data['adults_count'], 'children_count' => (int) ($data['children_count'] ?? 0), 'total_price_cents' => $totalPrice, 'contact_phone' => $data['phone'], 'notes' => $data['notes'] ?? null])->save();
            if ((bool) ($data['save_phone_to_profile'] ?? false)) {
                $user->forceFill(['phone' => $data['phone']])->save();
            }

            return $lockedBooking->refresh()->load(['items.ticketType', 'event.categories']);
        });
    }
}
