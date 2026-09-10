<?php

namespace App\Services;

use App\Mail\OperationalMessage;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\OrganizerProfile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OperationalEmailService
{
    public function organizerReviewed(OrganizerProfile $profile): void
    {
        $subject = match ($profile->verification_status) {
            'verified' => 'Profilo Organizer approvato', 'changes_requested' => 'Integrazione richiesta', default => 'Profilo Organizer non approvato'
        };
        $body = $profile->verification_status === 'verified'
            ? 'Il tuo profilo Organizzatore è stato approvato. Ora puoi utilizzare le funzionalità Organizzatore di MòVi.'
            : ($profile->review_notes ?: $subject.'.');
        $this->send($profile->owner->email, $subject, $body);
    }

    public function bookingConfirmed(Booking $booking): void
    {
        $this->send($booking->user->email, 'Prenotazione confermata', "Prenotazione {$booking->booking_code} confermata per {$booking->event->title}.");
    }

    public function bookingCancelled(Booking $booking): void
    {
        $this->send($booking->user->email, 'Prenotazione annullata', "La prenotazione {$booking->booking_code} per {$booking->event->title} è stata annullata.");
    }

    public function eventCancelled(Notification $notification, string $recipient): bool
    {
        $startsAt = data_get($notification->payload, 'starts_at');
        $when = $startsAt ? ' Data prevista: '.date('d/m/Y H:i', strtotime((string) $startsAt)).'.' : '';

        return $this->send($recipient, 'Evento annullato', $notification->message.$when);
    }

    private function send(string $recipient, string $subject, string $body): bool
    {
        try {
            Mail::to($recipient)->queue(new OperationalMessage($subject, $body));

            return true;
        } catch (Throwable $exception) {
            Log::error('Operational email could not be queued.', [
                'mail_type' => $subject,
                'exception' => $exception,
            ]);

            return false;
        }
    }
}
