<?php

return [
    'enabled' => (bool) env('WHATSAPP_ENABLED', false),
    'provider' => env('WHATSAPP_PROVIDER', 'generic'),
    'endpoint' => env('WHATSAPP_API_ENDPOINT'),
    'token' => env('WHATSAPP_API_TOKEN'),
    'sender_id' => env('WHATSAPP_SENDER_ID'),
];
