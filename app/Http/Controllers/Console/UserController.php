<?php

namespace App\Http\Controllers\Console;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserDirectory;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admins manage every account. (Coordinators invite agents from phase 2.)
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $role = UserRole::tryFrom((string) $request->query('role'));

        $users = User::query()->with('ward.lga', 'lga')
            ->when($q !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->when($role, fn ($query) => $query->where('role', $role))
            ->orderByRaw("case role when 'admin' then 0 when 'strategist' then 1 when 'lga_leader' then 2 when 'ward_coordinator' then 3 else 4 end")
            ->orderBy('name')
            ->paginate(50)->withQueryString();

        return view('users.index', [
            'users' => $users,
            'q' => $q,
            'role' => $role,
            'counts' => User::query()->selectRaw('role, count(*) as n')->groupBy('role')->pluck('n', 'role'),
            'wards' => UserDirectory::wardOptions(),
            'lgas' => UserDirectory::lgaOptions(),
        ]);
    }

    public function store(Request $request, UserDirectory $directory): RedirectResponse
    {
        $attributes = $directory->attributes($request->validate($directory->rules()));
        $user = User::create([...$attributes, 'invited_by' => $request->user()->id]);

        Audit::record('users.create', "Added {$user->name} as {$user->role->label()}", ['user_id' => $user->id]);

        return redirect()->route('users')->with('status', "{$user->name} can now sign in.");
    }

    public function edit(User $user): View
    {
        return view('users.edit', [
            'user' => $user,
            'wards' => UserDirectory::wardOptions(),
            'lgas' => UserDirectory::lgaOptions(),
        ]);
    }

    public function update(Request $request, User $user, UserDirectory $directory): RedirectResponse
    {
        $attributes = $directory->attributes($request->validate($directory->rules($user)), $user);

        if ($user->is($request->user()) && $attributes['role'] !== UserRole::Admin) {
            return back()->with('error', 'You can’t take away your own admin role.');
        }

        $user->update($attributes);
        Audit::record('users.update', "Changed {$user->name}’s account ({$user->role->label()})", ['user_id' => $user->id]);

        return redirect()->route('users')->with('status', "{$user->name}’s account is saved.");
    }

    /**
     * Switch an account off (signs it out everywhere) or back on.
     */
    public function toggle(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You can’t switch off your own account.');
        }

        $user->forceFill(['disabled_at' => $user->isDisabled() ? null : now(), 'remember_token' => null])->save();
        Audit::record($user->isDisabled() ? 'users.disable' : 'users.enable', ($user->isDisabled() ? 'Switched off ' : 'Switched on ').$user->name, ['user_id' => $user->id]);

        return back()->with('status', $user->isDisabled() ? "{$user->name} is switched off and signed out." : "{$user->name} can sign in again.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You can’t delete your own account.');
        }

        $user->delete();
        Audit::record('users.delete', "Deleted {$user->name}’s account", ['user_id' => $user->id]);

        return redirect()->route('users')->with('status', "{$user->name}’s account is deleted.");
    }
}
