<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('app:about-domain', function (): void {
    $this->info('MòVi vertical slice: auth, organizers, geography, categories, events moderation.');
});
