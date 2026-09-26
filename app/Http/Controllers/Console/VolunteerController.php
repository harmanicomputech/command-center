<?php

namespace App\Http\Controllers\Console;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\Ward;
use App\Services\Invitations;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The volunteer inbox: people who offered to help. Coordinators call them
 * and, in one step, make them field agents (with an invite link).
 */
class VolunteerController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $status = array_key_exists((string) $request->query('status'), config('volunteers.statuses')) ? $request->query('status') : null;
        $base = Volunteer::query()->visibleTo($user);

        return view('volunteers.index', [
            'volunteers' => (clone $base)->with(['lga', 'ward', 'user'])
                ->when($status, fn ($query) => $query->where('status', $status), fn ($query) => $query->whereIn('status', ['new', 'contacted']))
                ->latest()->paginate(30)->withQueryString(),
            'status' => $status,
            'counts' => (clone $base)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'week' => (clone $base)->where('created_at', '>=', now()->subWeek())->count(),
            'wards' => Ward::query()->visibleTo($user)->with('lga')->orderBy('name')->get(),
            'canInvite' => $user->role !== UserRole::Strategist,
        ]);
    }

    public function status(Request $request, Volunteer $volunteer): RedirectResponse
    {
        $this->authorizeVolunteer($request->user(), $volunteer);
        $data = $request->validate(['status' => ['required', Rule::in(['new', 'contacted', 'not_now'])]]);
        $volunteer->forceFill(['status' => $data['status'], 'handled_by' => $request->user()->id, 'handled_at' => now()])->save();

        return back()->with('status', "{$volunteer->name}: ".mb_strtolower($volunteer->statusLabel()).'.');
    }

    /** Make the volunteer a field agent in a ward, and show their invite link. */
    public function invite(Request $request, Volunteer $volunteer, Invitations $invitations): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeVolunteer($user, $volunteer);
        abort_if($user->role === UserRole::Strategist, 403);

        $ward = Ward::query()->visibleTo($user)->find($request->integer('ward_id') ?: $volunteer->ward_id);
        if (! $ward) {
            return back()->withErrors(['ward_id' => 'Choose the ward they will work in.']);
        }
        if (User::query()->where('phone', $volunteer->phone)->exists()) {
            return back()->with('error', 'Someone on the team already has this number.');
        }

        $member = User::create([
            'name' => $volunteer->name,
            'phone' => $volunteer->phone,
            'role' => UserRole::Agent,
            'ward_id' => $ward->id,
            'lga_id' => $ward->lga_id,
            'password' => Str::random(40),
            'invited_by' => $user->id,
        ]);
        $volunteer->forceFill(['status' => 'joined', 'user_id' => $member->id, 'ward_id' => $ward->id, 'handled_by' => $user->id, 'handled_at' => now()])->save();
        $link = $invitations->issue($member);
        Audit::record('volunteers.invite', "Made volunteer {$member->name} a field agent in {$ward->fullName()}", ['user_id' => $member->id]);

        return redirect()->route('team')->with('invite', TeamController::share($member, $link, $invitations));
    }

    private function authorizeVolunteer(User $user, Volunteer $volunteer): void
    {
        abort_unless(Volunteer::query()->visibleTo($user)->whereKey($volunteer->id)->exists(), 403);
    }
}
