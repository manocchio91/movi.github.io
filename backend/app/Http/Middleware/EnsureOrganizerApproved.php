<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizerApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $profile = $request->user()?->organizerProfile;

        abort_unless($profile !== null, 403, 'Profilo organizzatore richiesto.');
        abort_unless($profile->verification_status === 'verified', 403, 'Profilo organizzatore non approvato.');

        return $next($request);
    }
}
