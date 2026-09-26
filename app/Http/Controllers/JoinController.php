<?php

namespace App\Http\Controllers;

use App\Models\Lga;
use App\Services\VolunteerIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Volunteer sign-up: the public /join page, and an endpoint the campaign
 * website's own form handler forwards sign-ups to (server to server, with
 * the token shown on the System page).
 */
class JoinController extends Controller
{
    public static function token(): string
    {
        return substr(hash_hmac('sha256', 'command-center-volunteers', (string) config('app.key')), 0, 32);
    }

    public function show(): View
    {
        return view('join', ['lgas' => Lga::query()->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $request, VolunteerIntake $intake): RedirectResponse
    {
        // Bots fill every field; people never see this one.
        if (filled($request->input('website'))) {
            return back()->with('joined', true);
        }

        $intake->record($request->all(), 'join page');

        return redirect()->route('join')->with('joined', true);
    }

    public function api(Request $request, string $token, VolunteerIntake $intake): JsonResponse
    {
        abort_unless(hash_equals(self::token(), $token), 404);

        if (filled($request->input('website'))) {
            return response()->json(['status' => 'ok']);
        }

        try {
            $intake->record($request->all(), 'website');
        } catch (ValidationException $e) {
            return response()->json(['status' => 'invalid', 'errors' => $e->errors()], 422);
        }

        return response()->json(['status' => 'ok']);
    }
}
