<?php

namespace App\Services;

use App\Mail\OperationalMessage;
use App\Models\Booking;
use App\Models\OrganizerProfile;
use Illuminate\Support\Facades\Mail;

class OperationalEmailService
{
    public function organizerReviewed(OrganizerProfile $profile): void
    {
        $subject = match ($profile->verification_status) {
            'verified' => 'Profilo Organizer approvato', 'changes_requested' => 'Integrazione richiesta', default => 'Profilo Organizer non approvato'
        };
        $this->send($profile->owner->email, $subject, $profile->review_notes ?: $subject.'.');
    }

    public function bookingConfirmed(Booking $booking): void
    {
        $this->send($booking->user->email, 'Prenotazione confermata', "Prenotazione {$booking->booking_code} confermata per {$booking->event->title}.");
    }

    public function bookingCancelled(Booking $booking): void
    {
        $this->send($booking->user->email, 'Prenotazione annullata', "La prenotazione {$booking->booking_code} per {$booking->event->title} è stata annullata.");
    }

    private function send(string $recipient, string $subject, string $body): void
    {
        Mail::to($recipient)->send(new OperationalMessage($subject, $body));
    }
}
