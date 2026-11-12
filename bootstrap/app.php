<?php

use App\Http\Middleware\RequireRole;
use App\Http\Middleware\RequireStaff;
use App\Http\Middleware\RunBackgroundWork;
use App\Http\Middleware\TrackActivity;
use App\Support\Migrations;
use Illuminate\Database\QueryException;
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
        $middleware->validateCsrfTokens(except: ['api/poll/*', 'api/sms/*', 'api/volunteers/*']);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Uploaded code whose database update hasn't run yet: admins go to
        // the Update database button, everyone else waits a moment.
        $exceptions->render(function (QueryException $e, Request $request) {
            if (Migrations::pending() === []) {
                return null;
            }
            if (! $request->expectsJson() && ! $request->is('api/*') && $request->user()?->isAdmin()) {
                return redirect()->route('system.update');
            }

            return $request->expectsJson() || $request->is('api/*')
                ? response()->json(['status' => 'error', 'message' => 'The app is being updated. Try again in a few minutes.'], 503)
                : response()->view('errors.updating', [], 503);
        });
    })->create();
