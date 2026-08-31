<?php

use App\Actions\Waitlist\ExpireWaitlistOffers;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('app:about-domain', function (): void {
    $this->info('MòVi vertical slice: auth, organizers, geography, categories, events moderation.');
});

Schedule::call(fn () => app(ExpireWaitlistOffers::class)->handle())
    ->name('expire-waitlist-offers')->everyMinute()->withoutOverlapping();
