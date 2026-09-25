<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out disabled accounts (on every device), and records when a user was
 * last seen (at most every five minutes, to keep writes down).
 */
class TrackActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isDisabled()) {
            Auth::logout();
            $request->session()->invalidate();

            return $request->expectsJson()
                ? response()->json(['message' => 'This account has been switched off.'], 401)
                : redirect()->route('login')->with('error', 'This account has been switched off. Ask your coordinator.');
        }

        // "Sign out every device": sessions from before the revoke end here.
        if ($user && $request->hasSession()) {
            $authAt = $request->session()->get('auth_at');
            if ($authAt === null) {
                $request->session()->put('auth_at', $authAt = now()->timestamp);
            }
            if ($user->sessions_revoked_at && $user->sessions_revoked_at->timestamp > $authAt) {
                Auth::logout();
                $request->session()->invalidate();

                return $request->expectsJson()
                    ? response()->json(['message' => 'Signed out. Sign in again.'], 401)
                    : redirect()->route('login')->with('error', 'You were signed out of this device. Sign in again.');
            }
        }

        if ($user && (! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinutes(5)))) {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
