<?php

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OperationalMessage extends Mailable implements ShouldQueue
{
    public function __construct(
        private readonly string $messageSubject,
        private readonly string $messageBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->messageSubject);
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>'.e($this->messageBody).'</p>');
    }
}
