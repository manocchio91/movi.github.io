<?php

use App\Http\Middleware\EnsureOrganizerApproved;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\RequestId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/health',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1')))));
        $middleware->api(prepend: [
            RequestId::class,
        ]);
        $middleware->alias([
            'role' => EnsureUserRole::class,
            'organizer.approved' => EnsureOrganizerApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $exception): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            $status = match (true) {
                $exception instanceof ValidationException => 422,
                $exception instanceof AuthenticationException => 401,
                $exception instanceof AuthorizationException => 403,
                $exception instanceof ModelNotFoundException => 404,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };

            $message = match ($status) {
                401 => 'Non autenticato.',
                403 => $exception->getMessage() ?: 'Accesso non autorizzato.',
                404 => 'Risorsa non trovata.',
                409 => $exception->getMessage() ?: 'Conflitto con lo stato corrente della risorsa.',
                422 => 'I dati forniti non sono validi.',
                429 => 'Troppe richieste. Riprova più tardi.',
                500 => 'Non siamo riusciti a completare la richiesta. Riprova più tardi.',
                default => $exception->getMessage() ?: 'Richiesta non completata.',
            };

            return response()->json([
                'message' => $message,
                'errors' => $exception instanceof ValidationException ? $exception->errors() : [],
                'request_id' => $request->attributes->get('request_id'),
            ], $status);
        });
    })
    ->create();
