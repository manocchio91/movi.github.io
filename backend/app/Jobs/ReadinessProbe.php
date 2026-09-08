<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class ReadinessProbe implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function handle(): void
    {
        Cache::put('readiness:queue:last_ok', now()->toIso8601String(), now()->addMinutes(10));
    }
}
