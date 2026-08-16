<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (Request $request) {
            session()->flash('status', 'Faça login para continuar.');

            return route('login');
        });

        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpException $e, Request $request) {
            // Laravel's own exception handler converts TokenMismatchException
            // into a generic HttpException(419, ...) before custom render
            // callbacks run, so this must match on status code rather than
            // on the original exception type.
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            session()->flash('status', 'Sua sessão expirou. Faça login novamente.');

            return redirect()->route('login');
        });

        $exceptions->render(function (PDOException $e, Request $request) {
            return response()->view('errors.503', [], 503);
        });
    })->create();
