<?php

namespace App\Services\WhatsApp;

use App\Contracts\OrganizerWhatsAppProvider;
use Illuminate\Support\Facades\Http;

class HttpOrganizerWhatsAppProvider implements OrganizerWhatsAppProvider
{
    public function send(array $payload): void
    {
        Http::withToken((string) config('whatsapp.token'))
            ->post((string) config('whatsapp.endpoint'), $payload)
            ->throw();
    }
}
