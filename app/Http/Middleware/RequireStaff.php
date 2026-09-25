<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Command Center is for coordinators and above; field agents use the
 * Field Force app.
 */
class RequireStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role->usesFieldApp()) {
            if ($request->isMethod('GET') && ! $request->expectsJson() && $request->routeIs('dashboard')) {
                return redirect()->route('field.home');
            }

            abort(403, 'This page is for coordinators and leaders.');
        }

        return $next($request);
    }
}
