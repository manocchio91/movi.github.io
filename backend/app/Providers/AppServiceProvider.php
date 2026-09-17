<?php

namespace App\Providers;

use App\Contracts\OrganizerWhatsAppProvider;
use App\Services\MoviAi\AiIntentProvider;
use App\Services\MoviAi\ConfiguredAiIntentProvider;
use App\Services\WhatsApp\FakeOrganizerWhatsAppProvider;
use App\Services\WhatsApp\HttpOrganizerWhatsAppProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiIntentProvider::class, ConfiguredAiIntentProvider::class);
        $this->app->singleton(OrganizerWhatsAppProvider::class, fn () => config('whatsapp.provider') === 'fake'
            ? new FakeOrganizerWhatsAppProvider
            : new HttpOrganizerWhatsAppProvider);
    }

    public function boot(): void
    {
        RateLimiter::for('auth', function (Request $request): array {
            $email = Str::lower(trim((string) $request->input('email')));
            $ip = $request->ip() ?? 'unknown';

            return [
                Limit::perMinute(10)->by(($email !== '' ? $email : 'missing-email').'|'.$ip),
                Limit::perMinute(30)->by($ip),
            ];
        });

        RateLimiter::for('sensitive', fn (Request $request): array => [
            Limit::perMinute(30)->by((string) ($request->user()->id ?? $request->ip() ?? 'unknown')),
            Limit::perMinute(90)->by((string) ($request->ip() ?? 'unknown')),
        ]);
    }
}
