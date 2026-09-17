<?php

use App\Actions\Reservations\SendReservationReminders;
use App\Actions\Waitlist\ExpireWaitlistOffers;
use App\Jobs\ReadinessProbe;
use App\Jobs\SendPostVisitReviewRequestsJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('app:about-domain', function (): void {
    $this->info('MòVi vertical slice: auth, organizers, geography, categories, events moderation.');
});

Artisan::command('app:queue-smoke', function (): int {
    Cache::forget('readiness:queue:last_ok');
    ReadinessProbe::dispatch();
    $this->info('Queue readiness probe dispatched.');

    return 0;
});

Artisan::command('app:queue-smoke-status', function (): int {
    if (! Cache::has('readiness:queue:last_ok')) {
        $this->error('Queue readiness probe not processed.');

        return 1;
    }
    $this->info('Queue readiness probe processed.');

    return 0;
});

Schedule::call(fn () => app(ExpireWaitlistOffers::class)->handle())
    ->name('expire-waitlist-offers')->everyMinute()->withoutOverlapping();

Schedule::call(fn () => app(SendReservationReminders::class)->handle())
    ->name('send-reservation-reminders')->everyFiveMinutes()->withoutOverlapping();

Schedule::job(new SendPostVisitReviewRequestsJob)
    ->name('send-post-visit-review-requests')->hourly()->withoutOverlapping();
