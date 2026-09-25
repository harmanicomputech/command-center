<?php

namespace App\Http\Controllers\Console;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\PollingUnit;
use App\Models\User;
use App\Services\PollingUnitImporter;
use App\Support\Audit;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

/**
 * Sign-in. Staff use email and password; field agents their phone number
 * and PIN. On a fresh install there are no accounts: the first admin is
 * created with ADMIN_PASSWORD from .env as a one-time setup key, which also
 * runs the migrations and loads the PU register (the host has no terminal).
 */
class AuthController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        if (! $this->databaseReachable()) {
            return view('auth.login', ['mode' => 'no-database']);
        }

        if (! $this->hasUsers()) {
            abort_if(blank(config('campaign.admin_password')), 404);

            return view('auth.login', ['mode' => 'setup']);
        }

        return view('auth.login', ['mode' => 'login']);
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [], ['login' => 'email or phone number']);

        $login = trim($validated['login']);
        $user = str_contains($login, '@')
            ? User::query()->where('email', strtolower($login))->first()
            : User::query()->where('phone', Phone::normalize($login) ?? '-')->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            Audit::record('auth.failed', 'Failed sign-in for '.(str_contains($login, '@') ? strtolower($login) : Phone::mask($login)), actor: 'Unknown');

            return back()->withInput($request->only('login'))->withErrors(['login' => 'That sign-in didn’t match. Check the details and try again.']);
        }

        if ($user->isDisabled()) {
            return back()->withInput($request->only('login'))->withErrors(['login' => 'This account has been switched off. Ask your coordinator.']);
        }

        // Field agents stay signed in on their phone so they can work offline.
        Auth::login($user, remember: $request->boolean('remember') || $user->role->usesFieldApp());
        $request->session()->regenerate();
        $request->session()->put('auth_at', now()->timestamp);
        $user->forceFill(['last_login_at' => now(), 'last_seen_at' => now()])->saveQuietly();
        Audit::record('auth.login', 'Signed in');

        return redirect()->intended($user->role->usesFieldApp() ? route('field.home') : route('dashboard'));
    }

    public function setup(Request $request): RedirectResponse
    {
        $key = (string) config('campaign.admin_password');
        abort_if($key === '', 404);

        $validated = $request->validate([
            'setup_key' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);

        if (! hash_equals($key, $validated['setup_key'])) {
            return back()->withInput($request->only('name', 'email'))
                ->withErrors(['setup_key' => 'Wrong setup key. It is ADMIN_PASSWORD in the .env file.']);
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Could not set up the database: '.$e->getMessage());
        }

        if ($this->hasUsers()) {
            return redirect()->route('login')->with('error', 'An account already exists. Sign in instead.');
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => $validated['password'],
            'role' => UserRole::Admin,
        ]);

        // The register ships with the app: LGAs, wards and polling units.
        $imported = '';
        try {
            if (! PollingUnit::query()->exists()) {
                @set_time_limit(120);
                $result = app(PollingUnitImporter::class)->import(PollingUnitImporter::bundledPath());
                $imported = " The register of {$result['created']} polling units is loaded.";
            }
        } catch (Throwable $e) {
            report($e);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        Audit::record('auth.setup', 'Created the first admin account');

        return redirect()->route('system')->with('status', "Welcome, {$user->firstName()}.{$imported} Check the register below, then add your team under Users.");
    }

    public function logout(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            Audit::record('auth.logout', 'Signed out');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('logged_out', true);
    }

    private function databaseReachable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function hasUsers(): bool
    {
        try {
            return User::query()->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
