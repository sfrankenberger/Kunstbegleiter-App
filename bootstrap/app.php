<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        //
    })->create();
