<?php

namespace App\Http\Controllers\Console;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Lga;
use App\Models\Reward;
use App\Models\User;
use App\Services\Points;
use App\Services\Review;
use App\Support\Audit;
use App\Support\Time;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Leaderboards (agents, wards, LGAs; this week or all time), rewards for
 * the weekly winners, and the review of suspicious patterns.
 */
class LeaderboardController extends Controller
{
    public function index(Request $request, Points $points): View
    {
        $user = $request->user();
        $level = in_array($request->query('level'), ['agents', 'wards', 'lgas'], true) ? $request->query('level') : 'agents';
        $period = $request->query('period') === 'all' ? 'all' : 'week';
        $since = $period === 'week' ? Points::weekStart() : null;
        $lgaId = $user->role->isStatewide() ? null : ($user->lga_id ?? $user->ward?->lga_id);

        $rows = match ($level) {
            'agents' => $points->agents($user->role === UserRole::WardCoordinator ? $user->ward_id : null, $lgaId, $since),
            'wards' => $points->areas('ward', $since, $lgaId),
            'lgas' => $points->areas('lga', $since),
        };

        return view('leaderboard.index', [
            'rows' => $rows,
            'level' => $level,
            'period' => $period,
            'scopeLabel' => $user->role->isStatewide() ? 'Across Ebonyi' : ($user->role === UserRole::WardCoordinator && $level === 'agents' ? 'In '.$user->ward?->name : 'In '.Lga::query()->find($lgaId)?->name),
            'weekOf' => Time::now()->startOfWeek(),
            'rewards' => Reward::query()->with('user', 'giver')->latest()->limit(8)->get(),
            'canReward' => $user->isAdmin(),
        ]);
    }

    public function review(Request $request, Review $review): View
    {
        abort_unless($request->user()->hasRole(UserRole::Admin, UserRole::LgaLeader, UserRole::WardCoordinator), 403);

        return view('leaderboard.review', ['flags' => $review->flags($request->user())]);
    }

    public function reward(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'kind' => ['required', Rule::in(array_keys(config('field.reward_kinds')))],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $reward = Reward::create([...$data, 'week_of' => Time::now()->startOfWeek()->subWeek()->toDateString(), 'given_by' => $request->user()->id]);
        $name = User::query()->find($data['user_id'])->name;
        Audit::record('rewards.create', "Recorded a reward for {$name}: {$reward->kindLabel()}", ['reward_id' => $reward->id]);

        return back()->with('status', "Reward recorded for {$name}.");
    }
}
