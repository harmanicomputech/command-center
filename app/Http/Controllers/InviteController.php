<?php

namespace App\Http\Controllers;

use App\Services\Invitations;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * An invited agent opens their link, chooses a PIN and is signed in.
 */
class InviteController extends Controller
{
    public function show(string $token, Invitations $invitations): View
    {
        return view('auth.invite', ['member' => $invitations->find($token), 'token' => $token]);
    }

    public function accept(Request $request, string $token, Invitations $invitations): RedirectResponse
    {
        $member = $invitations->find($token);
        abort_if($member === null, 410, 'This invite link has expired. Ask your coordinator for a new one.');

        $pin = $request->validate(
            ['pin' => ['required', 'confirmed', 'regex:/^\d{4,6}$/']],
            ['pin.regex' => 'A PIN is 4 to 6 digits.', 'pin.confirmed' => 'The two PINs don’t match.'],
        )['pin'];

        $member->forceFill([
            'password' => $pin,
            'invite_token_hash' => null,
            'invite_expires_at' => null,
            'invite_accepted_at' => now(),
            'last_login_at' => now(),
        ])->save();

        Auth::login($member, remember: true);
        $request->session()->regenerate();
        $request->session()->put('auth_at', now()->timestamp);
        Audit::record('team.accept', 'Accepted their invite and chose a PIN');

        return redirect()->route($member->role->usesFieldApp() ? 'field.home' : 'dashboard')->with('status', "Welcome, {$member->firstName()}. You’re signed in on this phone.");
    }
}
