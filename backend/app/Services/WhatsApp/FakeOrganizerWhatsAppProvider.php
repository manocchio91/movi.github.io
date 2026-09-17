<?php

namespace App\Services\WhatsApp;

use App\Contracts\OrganizerWhatsAppProvider;

class FakeOrganizerWhatsAppProvider implements OrganizerWhatsAppProvider
{
    /** @var array<int, array<string, mixed>> */
    private array $sent = [];

    public function send(array $payload): void
    {
        $this->sent[] = $payload;
    }

    /** @return array<int, array<string, mixed>> */
    public function sent(): array
    {
        return $this->sent;
    }
}
