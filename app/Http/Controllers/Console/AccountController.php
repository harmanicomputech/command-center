<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        return view($request->user()->role->usesFieldApp() ? 'field.me' : 'account.show', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->user()->update($request->validate(['name' => ['required', 'string', 'max:120']]));

        return back()->with('status', 'Your name is saved.');
    }

    public function password(Request $request): RedirectResponse
    {
        $agent = $request->user()->role->usesFieldApp();
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => $agent ? ['required', 'confirmed', 'regex:/^\d{4,6}$/'] : ['required', 'confirmed', Password::min(10)],
        ], ['password.regex' => 'A PIN is 4 to 6 digits.', 'current_password.current_password' => 'That isn’t your current '.($agent ? 'PIN' : 'password').'.']);

        $request->user()->update(['password' => $validated['password']]);
        Audit::record('account.password', $agent ? 'Changed their PIN' : 'Changed their password');

        return back()->with('status', $agent ? 'Your PIN is changed.' : 'Your password is changed.');
    }
}
