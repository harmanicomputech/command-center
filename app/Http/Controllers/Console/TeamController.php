<?php

namespace App\Http\Controllers\Console;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Voter;
use App\Models\Ward;
use App\Services\Invitations;
use App\Support\Audit;
use App\Support\Phone;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Coordinators invite agents in their ward; LGA leaders invite coordinators
 * and agents in their LGA; admins anyone. Also: a new invite link, and
 * signing a lost phone out.
 */
class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $members = $user->limitToArea(User::query(), 'users.ward_id')
            ->whereIn('role', [UserRole::WardCoordinator, UserRole::Agent])
            ->whereKeyNot($user->id)
            ->with('ward.lga')
            ->orderByRaw("case role when 'ward_coordinator' then 0 else 1 end")->orderBy('name')
            ->get();

        $registrations = Voter::query()->counted()->whereIn('captured_by', $members->pluck('id'))
            ->selectRaw('captured_by, count(*) as n')->groupBy('captured_by')->pluck('n', 'captured_by');

        return view('team.index', [
            'members' => $members,
            'registrations' => $registrations,
            'wards' => Ward::query()->visibleTo($user)->with('lga')->orderBy('name')->get()->mapWithKeys(fn (Ward $ward) => [$ward->id => $user->role->isStatewide() || $user->role === UserRole::LgaLeader ? $ward->fullName() : $ward->name])->all(),
            'roles' => $this->invitableRoles($user),
            'invite' => session('invite'),
        ]);
    }

    public function store(Request $request, Invitations $invitations): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'role' => ['required', Rule::in(array_map(fn (UserRole $role) => $role->value, $this->invitableRoles($user)))],
            'ward_id' => ['required', 'integer'],
        ]);

        $phone = Phone::normalize($data['phone']);
        if ($phone === null) {
            return back()->withInput()->withErrors(['phone' => 'Enter a Nigerian mobile number, e.g. 0803 123 4567.']);
        }
        if (User::query()->where('phone', $phone)->exists()) {
            return back()->withInput()->withErrors(['phone' => 'Someone already has this phone number. Send them a new link from the list instead.']);
        }

        $ward = Ward::query()->visibleTo($user)->find($data['ward_id']);
        if (! $ward) {
            return back()->withInput()->withErrors(['ward_id' => 'Choose a ward in your area.']);
        }

        $member = User::create([
            'name' => trim($data['name']),
            'phone' => $phone,
            'role' => UserRole::from($data['role']),
            'ward_id' => $ward->id,
            'lga_id' => $ward->lga_id,
            'password' => Str::random(40),
            'invited_by' => $user->id,
        ]);
        $link = $invitations->issue($member);
        Audit::record('team.invite', "Invited {$member->name} as {$member->role->label()} in {$ward->fullName()}", ['user_id' => $member->id]);

        return redirect()->route('team')->with('invite', self::share($member, $link, $invitations));
    }

    public function reinvite(Request $request, User $member, Invitations $invitations): RedirectResponse
    {
        $this->authorizeMember($request->user(), $member);
        $link = $invitations->issue($member);
        Audit::record('team.reinvite', "Sent {$member->name} a new sign-in link", ['user_id' => $member->id]);

        return redirect()->route('team')->with('invite', self::share($member, $link, $invitations));
    }

    /**
     * A lost or shared phone: sign this person out everywhere. They sign back
     * in with their phone number and PIN, or a new invite link.
     */
    public function revoke(Request $request, User $member): RedirectResponse
    {
        $this->authorizeMember($request->user(), $member);
        $member->forceFill(['sessions_revoked_at' => now(), 'remember_token' => Str::random(60)])->save();
        Audit::record('team.revoke', "Signed {$member->name} out of every device", ['user_id' => $member->id]);

        return back()->with('status', "{$member->name} is signed out on every device.");
    }

    /**
     * @return list<UserRole>
     */
    private function invitableRoles(User $user): array
    {
        return $user->atLeast(UserRole::LgaLeader) ? [UserRole::Agent, UserRole::WardCoordinator] : [UserRole::Agent];
    }

    private function authorizeMember(User $user, User $member): void
    {
        $inArea = $member->ward !== null && $user->canSeeWard($member->ward);
        $below = $member->role->rank() < $user->role->rank() || $user->isAdmin();

        abort_unless($inArea && $below, 403, 'This person is outside your team.');
    }

    /**
     * @return array{name: string, link: string, message: string, phone: string}
     */
    public static function share(User $member, string $link, Invitations $invitations): array
    {
        return [
            'name' => $member->name,
            'link' => $link,
            'message' => $invitations->message($member, $link, Settings::get('campaign.name') ?: config('app.name')),
            'phone' => (string) $member->phone,
        ];
    }
}
