<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Field\FieldSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Field Force outbox endpoint. Session-authenticated (agents stay
 * signed in for 30 days); the outbox fetches a fresh CSRF token first,
 * because a page opened days ago offline carries a stale one.
 */
class FieldSyncController extends Controller
{
    public function token(Request $request): JsonResponse
    {
        return response()->json(['token' => csrf_token(), 'user' => $request->user()->id], 200, ['Cache-Control' => 'no-store']);
    }

    public function sync(Request $request, FieldSync $sync): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'max:'.FieldSync::MAX_ITEMS],
            'items.*' => ['array'],
        ]);

        return response()->json([
            'results' => $sync->apply($request->user(), $validated['items']),
            'server_time' => now()->toIso8601String(),
        ], 200, ['Cache-Control' => 'no-store']);
    }
}
