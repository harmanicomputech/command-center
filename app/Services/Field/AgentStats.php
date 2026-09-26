<?php

namespace App\Services\Field;

use App\Models\User;
use App\Models\Voter;
use App\Services\Points;
use App\Support\Time;
use Illuminate\Support\Carbon;

/**
 * The agent's own numbers for the field home screen: today, this week, the
 * streak (consecutive Lagos days with a registration) and their rank on
 * this week's ward leaderboard. Invalid records and unresolved duplicates don't count.
 */
class AgentStats
{
    /**
     * @return array{today: int, week: int, total: int, streak: int, rank: ?int, ranked: int}
     */
    public function for(User $user): array
    {
        $startOfDay = Time::now()->startOfDay()->utc();
        $startOfWeek = Time::now()->startOfWeek()->utc();
        $mine = Voter::query()->counted()->where('captured_by', $user->id);

        return [
            'today' => (clone $mine)->where('captured_at', '>=', $startOfDay)->count(),
            'week' => (clone $mine)->where('captured_at', '>=', $startOfWeek)->count(),
            'total' => (clone $mine)->count(),
            'streak' => $this->streak($user),
            ...$this->rank($user, $startOfWeek),
        ];
    }

    private function streak(User $user): int
    {
        $days = Voter::query()->counted()->where('captured_by', $user->id)
            ->where('captured_at', '>=', now()->subDays(90))
            ->pluck('captured_at')
            ->map(fn (Carbon $at) => $at->copy()->setTimezone(Time::zone())->format('Y-m-d'))
            ->unique()->flip();

        $day = Time::now();
        // A streak still counts this morning if it ran until yesterday.
        if (! $days->has($day->format('Y-m-d'))) {
            $day = $day->subDay();
        }

        $streak = 0;
        while ($days->has($day->format('Y-m-d'))) {
            $streak++;
            $day = $day->subDay();
        }

        return $streak;
    }

    /**
     * The agent's place on this week's ward leaderboard (by points).
     *
     * @return array{rank: ?int, ranked: int}
     */
    private function rank(User $user, Carbon $since): array
    {
        if ($user->ward_id === null) {
            return ['rank' => null, 'ranked' => 0];
        }

        $board = app(Points::class)->agents($user->ward_id, null, $since)->filter(fn ($row) => $row['points'] > 0);
        $mine = $board->first(fn ($row) => $row['user']->id === $user->id);

        return ['rank' => $mine['rank'] ?? null, 'ranked' => $board->count()];
    }
}
