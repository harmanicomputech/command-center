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

        if ($user && (! $user->last_seen_at || $user->last_seen_at->lt(now()->subMinutes(5)))) {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
