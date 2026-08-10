<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;

return ['default' => env('LOG_CHANNEL', 'stack'), 'channels' => [
    'stack' => ['driver' => 'stack', 'channels' => explode(',', (string) env('LOG_STACK', 'daily')), 'ignore_exceptions' => false],
    'daily' => ['driver' => 'daily', 'path' => storage_path('logs/laravel.log'), 'level' => env('LOG_LEVEL', 'info'), 'days' => (int) env('LOG_DAILY_DAYS', 14), 'replace_placeholders' => true],
    'stderr' => ['driver' => 'monolog', 'level' => env('LOG_LEVEL', 'info'), 'handler' => StreamHandler::class, 'with' => ['stream' => 'php://stderr']],
    'null' => ['driver' => 'monolog', 'handler' => NullHandler::class], 'emergency' => ['path' => storage_path('logs/laravel.log')],
]];
