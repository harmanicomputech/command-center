<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Issue;
use App\Models\Lga;
use App\Models\SurveyResponse;
use App\Models\TaskReport;
use App\Models\User;
use App\Models\Voter;
use App\Models\Ward;
use App\Support\Settings;
use App\Support\Time;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Points, worked out from the records themselves (so verifying or
 * invalidating a registration changes the score at once):
 *
 *   verified registration 10 · unverified 3 (invalid and unresolved
 *   duplicates 0) · task done with proof 15 · accepted issue 5 ·
 *   event attended 5 · survey response collected 2 (Settings → Points)
 *
 * The weekly board starts Monday 00:00 Lagos time, so it "resets" by itself.
 */
class Points
{
    public static function weekStart(): Carbon
    {
        return Time::now()->startOfWeek()->utc();
    }

    /**
     * @param  list<int>|null  $userIds  null for everyone
     * @return Collection<int, array{points: int, registrations: int, verified: int, tasks: int, issues: int, events: int}>
     */
    public function totals(?array $userIds = null, ?Carbon $since = null, ?Carbon $until = null): Collection
    {
        $window = function ($query, string $column) use ($since, $until) {
            $since && $query->where($column, '>=', $since);
            $until && $query->where($column, '<', $until);

            return $query;
        };
        $only = fn ($query, string $column) => $userIds === null ? $query : $query->whereIn($column, $userIds);

        $registrations = $window($only(Voter::query()->counted(), 'captured_by'), 'captured_at')
            ->selectRaw("captured_by as user_id, count(*) as n, sum(case when status = 'verified' then 1 else 0 end) as verified")
            ->groupBy('captured_by')->get()->keyBy('user_id');

        $tasks = $window($only(TaskReport::query()->where('done', true), 'user_id'), 'reported_at')
            ->selectRaw('user_id, count(distinct task_id) as n')->groupBy('user_id')->pluck('n', 'user_id');

        $issues = $window($only(Issue::query()->whereIn('status', Issue::ACCEPTED), 'reported_by'), 'reported_at')
            ->selectRaw('reported_by as user_id, count(*) as n')->groupBy('reported_by')->pluck('n', 'user_id');

        $events = $window($only(Event::query()->join('event_user', 'event_user.event_id', '=', 'events.id')->where('events.status', Event::HELD), 'event_user.user_id'), 'events.starts_at')
            ->selectRaw('event_user.user_id, count(*) as n')->groupBy('event_user.user_id')->pluck('n', 'user_id');

        $surveys = $window($only(SurveyResponse::query()->whereNotNull('collected_by'), 'collected_by'), 'answered_at')
            ->selectRaw('collected_by as user_id, count(*) as n')->groupBy('collected_by')->pluck('n', 'user_id');

        $value = [
            'verified' => Settings::int('points.registration_verified'),
            'unverified' => Settings::int('points.registration_unverified'),
            'task' => Settings::int('points.task'),
            'issue' => Settings::int('points.issue'),
            'event' => Settings::int('points.event'),
            'survey' => Settings::int('points.survey'),
        ];

        $ids = collect([$registrations->keys(), $tasks->keys(), $issues->keys(), $events->keys(), $surveys->keys()])->flatten()->unique();

        return $ids->mapWithKeys(function ($id) use ($registrations, $tasks, $issues, $events, $surveys, $value) {
            $all = (int) ($registrations[$id]->n ?? 0);
            $verified = (int) ($registrations[$id]->verified ?? 0);
            $row = [
                'registrations' => $all,
                'verified' => $verified,
                'tasks' => (int) ($tasks[$id] ?? 0),
                'issues' => (int) ($issues[$id] ?? 0),
                'events' => (int) ($events[$id] ?? 0),
                'surveys' => (int) ($surveys[$id] ?? 0),
            ];
            $row['points'] = $verified * $value['verified'] + ($all - $verified) * $value['unverified']
                + $row['tasks'] * $value['task'] + $row['issues'] * $value['issue'] + $row['events'] * $value['event'] + $row['surveys'] * $value['survey'];

            return [(int) $id => $row];
        });
    }

    /**
     * Agents ranked by points: in a ward, an LGA, or the whole state.
     *
     * @return Collection<int, array{user: User, rank: int, points: int, registrations: int, verified: int, tasks: int, issues: int, events: int}>
     */
    public function agents(?int $wardId = null, ?int $lgaId = null, ?Carbon $since = null): Collection
    {
        $agents = User::query()->where('role', UserRole::Agent)->whereNull('disabled_at')
            ->when($wardId, fn ($query) => $query->where('ward_id', $wardId))
            ->when($lgaId, fn ($query) => $query->where('lga_id', $lgaId))
            ->with('ward')->get()->keyBy('id');

        $totals = $this->totals($agents->keys()->all(), $since);
        $empty = ['points' => 0, 'registrations' => 0, 'verified' => 0, 'tasks' => 0, 'issues' => 0, 'events' => 0, 'surveys' => 0];

        return $this->rank($agents->map(fn (User $agent) => ['user' => $agent, ...($totals[$agent->id] ?? $empty)])
            ->sortBy([['points', 'desc'], ['registrations', 'desc'], [fn ($row) => $row['user']->name, 'asc']])->values());
    }

    /**
     * Wards (or LGAs) ranked by their agents' points.
     *
     * @return Collection<int, array{id: int, name: string, detail: string, rank: int, points: int, agents: int}>
     */
    public function areas(string $level, ?Carbon $since = null, ?int $lgaId = null): Collection
    {
        $column = $level === 'lga' ? 'lga_id' : 'ward_id';
        $agents = User::query()->where('role', UserRole::Agent)->whereNull('disabled_at')->whereNotNull($column)
            ->when($lgaId, fn ($query) => $query->where('lga_id', $lgaId))->get(['id', 'ward_id', 'lga_id']);
        $totals = $this->totals($agents->pluck('id')->all(), $since);

        $groups = $agents->groupBy($column)->map(fn ($members, $id) => [
            'id' => (int) $id,
            'points' => $members->sum(fn ($agent) => $totals[$agent->id]['points'] ?? 0),
            'agents' => $members->count(),
        ]);

        $names = $level === 'lga'
            ? Lga::query()->whereIn('id', $groups->keys())->get()->mapWithKeys(fn ($lga) => [$lga->id => [$lga->name, $lga->wards_count.' wards']])
            : Ward::query()->with('lga')->whereIn('id', $groups->keys())->get()->mapWithKeys(fn ($ward) => [$ward->id => [$ward->name, $ward->lga->name]]);

        return $this->rank($groups->map(fn ($row) => [...$row, 'name' => $names[$row['id']][0] ?? '?', 'detail' => $names[$row['id']][1] ?? ''])
            ->sortByDesc('points')->values());
    }

    /**
     * Equal points share a rank (1, 2, 2, 4).
     *
     * @template T of array{points: int}
     *
     * @param  Collection<int, T>  $rows
     * @return Collection<int, T>
     */
    private function rank(Collection $rows): Collection
    {
        $rank = 0;
        $previous = null;

        return $rows->values()->map(function ($row, $index) use (&$rank, &$previous) {
            if ($row['points'] !== $previous) {
                $rank = $index + 1;
                $previous = $row['points'];
            }

            return [...$row, 'rank' => $rank];
        });
    }
}
