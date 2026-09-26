<?php

namespace App\Http\Controllers\Console;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Lga;
use App\Models\User;
use App\Models\Voter;
use App\Models\Ward;
use App\Services\Structure;
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
    public function index(Request $request, Structure $structure): View
    {
        $user = $request->user();
        $health = $structure->wardHealth($user);
        $voters = Voter::query()->counted()->inAreaOf($user);
        $weekStart = Time::now()->startOfWeek()->utc();
        $daily = (clone $voters)->where('captured_at', '>=', now()->subDays(14))->pluck('captured_at')
            ->countBy(fn ($at) => $at->copy()->setTimezone(Time::zone())->format('Y-m-d'));
        $thisWeek = (clone $voters)->where('captured_at', '>=', $weekStart)->count();
        $lastWeek = (clone $voters)->whereBetween('captured_at', [$weekStart->copy()->subWeek(), $weekStart])->count();
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
            'canvassed' => (clone $voters)->count(),
            'canvassedToday' => (clone $voters)->where('captured_at', '>=', Time::now()->startOfDay()->utc())->count(),
            'canvassedWeek' => $thisWeek,
            'canvassedDelta' => $lastWeek > 0 ? round(100 * ($thisWeek - $lastWeek) / $lastWeek, 1) : null,
            'canvassedSpark' => collect(range(13, 0))->map(fn ($ago) => (int) ($daily[Time::now()->subDays($ago)->format('Y-m-d')] ?? 0))->all(),
            'redWards' => $health->where('red', true)->values(),
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
