<?php

namespace App\Contracts;

interface OrganizerWhatsAppProvider
{
    /** @param array<string, mixed> $payload */
    public function send(array $payload): void;
}
