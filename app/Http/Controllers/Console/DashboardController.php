<?php

namespace App\Http\Controllers\Console;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Lga;
use App\Models\User;
use App\Models\Ward;
use App\Support\Settings;
use App\Support\Time;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The leadership home page. Phase 5 turns it into the daily dashboard; until
 * then it shows the structure, the register and the set-up checklist.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $wards = Ward::query()->visibleTo($user);
        $team = $user->limitToArea(User::query(), 'users.ward_id');

        $wardsWithCoordinator = (clone $team)->where('role', UserRole::WardCoordinator)->distinct()->count('ward_id');
        $wardCount = (clone $wards)->count();

        return view('dashboard.index', [
            'greeting' => Time::greeting(),
            'daysToGo' => Time::daysToElection(),
            'registered' => (int) (clone $wards)->sum('registered_voters'),
            'wardCount' => $wardCount,
            'lgaCount' => Lga::query()->visibleTo($user)->count(),
            'agents' => (clone $team)->where('role', UserRole::Agent)->count(),
            'activeAgents' => (clone $team)->where('role', UserRole::Agent)->where('last_seen_at', '>=', now()->subDays(14))->count(),
            'coordinatorCoverage' => $wardCount ? round(100 * $wardsWithCoordinator / $wardCount) : 0,
            'wardsWithCoordinator' => $wardsWithCoordinator,
            'target' => Settings::int('target.total'),
            'setup' => $user->isAdmin() ? $this->setupSteps() : [],
        ]);
    }

    /**
     * @return list<array{label: string, done: bool, url: string}>
     */
    private function setupSteps(): array
    {
        return [
            ['label' => 'Load the polling unit register', 'done' => Ward::query()->exists(), 'url' => route('system')],
            ['label' => 'Confirm the register, or upload INEC’s', 'done' => filled(Settings::get('register.confirmed_at')), 'url' => route('system').'#register'],
            ['label' => 'Name the campaign and the candidate', 'done' => filled(Settings::get('campaign.candidate')), 'url' => route('settings')],
            ['label' => 'Set up the background runner (pinger)', 'done' => filled(Settings::get('runner.heartbeat')), 'url' => route('system').'#runner'],
            ['label' => 'Add LGA leaders and ward coordinators', 'done' => User::query()->whereIn('role', [UserRole::LgaLeader, UserRole::WardCoordinator])->exists(), 'url' => route('users')],
        ];
    }
}
