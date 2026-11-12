<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Agents enrolled from a list sign in with the last 4 digits of their phone,
 * which anyone who knows the number could guess: until they choose their own
 * PIN, the field app's pages lead to the "choose your PIN" form. Saving work
 * (the outbox sync) still goes through, so nothing is lost.
 */
class RequirePinChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_pin && $request->isMethod('GET') && ! $request->expectsJson()
            && ! $request->routeIs('field.me', 'logout', 'offline', 'privacy', 'photos.show') && ! $request->is('api/*')) {
            return redirect()->route('field.me', ['pin' => 1]);
        }

        return $next($request);
    }
}
