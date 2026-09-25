<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard: `role:admin` or `role:admin,strategist`. Scope inside an
 * allowed role (which LGA, which ward) is checked in the controllers.
 */
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_map(fn (string $role) => UserRole::from($role), $roles);

        abort_unless($request->user() && in_array($request->user()->role, $allowed, true), 403, 'Your role can’t open this page.');

        return $next($request);
    }
}
