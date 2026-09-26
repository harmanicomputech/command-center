<?php

namespace App\Services;

use App\Models\User;
use App\Models\Voter;
use App\Services\Field\AgentStats;
use App\Support\Time;

/**
 * Badges: First 10, 100 club, Ward champion (top of the ward last week) and
 * 7-day streak. Worked out on the fly.
 */
class Badges
{
    public const ALL = [
        'first_10' => ['First 10', 'Registered 10 voters', 'star'],
        'club_100' => ['100 club', 'Registered 100 voters', 'medal'],
        'ward_champion' => ['Ward champion', 'Top of the ward last week', 'trophy'],
        'streak_7' => ['7-day streak', 'Registered voters 7 days in a row', 'flame'],
    ];

    public function __construct(private Points $points, private AgentStats $stats) {}

    /**
     * @return array<string, bool> badge key → earned
     */
    public function for(User $user): array
    {
        $registered = Voter::query()->counted()->where('captured_by', $user->id)->count();

        return [
            'first_10' => $registered >= 10,
            'club_100' => $registered >= 100,
            'ward_champion' => $this->wasWardChampion($user),
            'streak_7' => $this->stats->for($user)['streak'] >= 7,
        ];
    }

    private function wasWardChampion(User $user): bool
    {
        if ($user->ward_id === null) {
            return false;
        }

        $lastWeek = Time::now()->startOfWeek()->subWeek()->utc();
        $agents = User::query()->where('ward_id', $user->ward_id)->where('role', $user->role)->pluck('id')->all();
        $totals = $this->points->totals($agents, $lastWeek, Points::weekStart());
        $best = $totals->sortByDesc('points')->first();

        return $best !== null && $best['points'] > 0 && ($totals[$user->id]['points'] ?? 0) === $best['points'];
    }
}
