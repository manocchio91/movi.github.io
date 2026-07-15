<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('app:about-domain', function (): void {
    $this->info('Eventi Locali vertical slice: auth, organizers, geography, categories, events moderation.');
});
