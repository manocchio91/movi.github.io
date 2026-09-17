<?php

namespace App\Jobs;

use App\Contracts\OrganizerWhatsAppProvider;
use App\Models\Booking;
use App\Models\Reservation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendOrganizerWhatsAppBookingNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $type, public readonly int $id) {}

    public function handle(OrganizerWhatsAppProvider $provider): void
    {
        if (! config('whatsapp.enabled')) {
            return;
        }

        try {
            [$recipient, $message] = $this->type === 'reservation' ? $this->reservationMessage() : $this->bookingMessage();
            if (! $recipient) {
                return;
            }

            if (config('whatsapp.provider') !== 'fake' && (! config('whatsapp.endpoint') || ! config('whatsapp.token'))) {
                return;
            }

            $provider->send([
                'sender_id' => config('whatsapp.sender_id'),
                'to' => $recipient,
                'message' => $message,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Organizer WhatsApp notification failed.', ['type' => $this->type, 'record_id' => $this->id, 'exception' => $exception::class]);
        }
    }

    /** @return array{string|null, string} */
    private function reservationMessage(): array
    {
        $reservation = Reservation::query()->with(['user', 'activity.organizerProfile'])->findOrFail($this->id);
        $preorder = $reservation->preorder()->where('status', 'confirmed')->exists() ? 'sì' : 'no';

        return [$reservation->activity->organizerProfile->phone ?? $reservation->activity->phone, "MòVi — Nuova prenotazione\nCliente: {$reservation->user->name}\nData: {$reservation->starts_at->format('d/m/Y')}\nOra: {$reservation->starts_at->format('H:i')}\nPersone: {$reservation->party_size}\nLocale: {$reservation->activity->name}\nPreordine: {$preorder}"];
    }

    /** @return array{string|null, string} */
    private function bookingMessage(): array
    {
        $booking = Booking::query()->with(['user', 'event.organizerProfile'])->findOrFail($this->id);

        return [$booking->event->organizerProfile->phone, "MòVi — Nuova prenotazione evento\nEvento: {$booking->event->title}\nCliente: {$booking->user->name}\nPartecipanti: {$booking->total_people}\nData: {$booking->event->starts_at->format('d/m/Y H:i')}"];
    }
}
