<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // An expired or already-used login form (e.g. a double submit right after a
        // successful login rotated the token) should not end on a bare 419 page.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || !$request->is('login')) {
                return null;
            }

            if (Auth::check()) {
                return redirect()->intended(url('/plaid'));
            }

            return redirect()->route('login')
                ->withErrors(['pin' => 'Your session expired. Please enter your PIN again.']);
        });
    })->create();
