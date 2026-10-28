<?php

use App\Support\ErrorAlert;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Nicht angemeldet: zur Anmeldung der Handy-Oberflaeche (Filament hat seine eigene unter /admin/login)
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo('/jetzt');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Unerwartete Ausnahmen gedrosselt an die Admins melden (docs/konzept.md Abschnitt 27), HTTP-Fehler bleiben beim Log
        $exceptions->report(function (Throwable $e): void {
            if ($e instanceof HttpExceptionInterface || $e instanceof TokenMismatchException) {
                return;
            }

            try {
                app(ErrorAlert::class)->report($e);
            } catch (Throwable) {
                // nie die eigentliche Fehlerbehandlung stoeren
            }
        });
    })->create();
