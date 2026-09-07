<?php

return [
    'provider' => env('AI_PROVIDER', 'none'),
    'model' => env('AI_MODEL'),
    'api_key' => env('AI_API_KEY'),
    'endpoint' => env('AI_ENDPOINT'),
    'timeout' => (int) env('AI_TIMEOUT', 5),
];
