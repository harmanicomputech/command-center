<?php

use App\Http\Middleware\RequireRole;
use App\Http\Middleware\RequireStaff;
use App\Http\Middleware\RunBackgroundWork;
use App\Http\Middleware\TrackActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(RunBackgroundWork::class);
        $middleware->web(append: [TrackActivity::class]);
        $middleware->alias(['role' => RequireRole::class, 'staff' => RequireStaff::class]);
        // Africa's Talking callbacks carry a secret in the URL instead of a CSRF token.
        $middleware->validateCsrfTokens(except: ['api/poll/*']);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
