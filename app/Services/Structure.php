<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Issue;
use App\Models\SurveyResponse;
use App\Models\TaskReport;
use App\Models\User;
use App\Models\Voter;
use App\Models\Ward;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Engagement per person and structure health per ward.
 *
 *  - Engagement (last 14 days): active = did something (registered a
 *    voter, attended an event, finished a task); occasional = signed in
 *    but did nothing; dormant = neither.
 *  - A ward is red when it has no coordinator or no activity in 7 days.
 */
class Structure
{
    public const ACTIVE = 'active';

    public const OCCASIONAL = 'occasional';

    public const DORMANT = 'dormant';

    public const LABELS = [
        self::ACTIVE => ['Active', 'good'],
        self::OCCASIONAL => ['Occasional', 'warn'],
        self::DORMANT => ['Dormant', null],
    ];

    /**
     * The last time each user did something (registration, attendance, and
     * later tasks), keyed by user id.
     *
     * @param  Collection<int, int>  $userIds
     * @return Collection<int, Carbon>
     */
    public function lastOutput(Collection $userIds): Collection
    {
        $times = collect();
        $ids = $userIds->values()->all();

        $registrations = Voter::query()->counted()->whereIn('captured_by', $ids)
            ->selectRaw('captured_by as user_id, max(captured_at) as at')->groupBy('captured_by')->pluck('at', 'user_id');

        $attended = Event::query()->join('event_user', 'event_user.event_id', '=', 'events.id')
            ->where('events.status', Event::HELD)->whereIn('event_user.user_id', $ids)
            ->selectRaw('event_user.user_id, max(events.starts_at) as at')->groupBy('event_user.user_id')->pluck('at', 'user_id');

        foreach ([$registrations, $attended, ...$this->extraOutputs($ids)] as $source) {
            foreach ($source as $userId => $at) {
                $time = Carbon::parse($at);
                if (! $times->has($userId) || $time->gt($times[$userId])) {
                    $times[$userId] = $time;
                }
            }
        }

        return $times;
    }

    /**
     * Task updates, issue reports and survey responses.
     *
     * @param  list<int>  $ids
     * @return list<Collection<int, string>>
     */
    protected function extraOutputs(array $ids): array
    {
        return [
            TaskReport::query()->whereIn('user_id', $ids)->selectRaw('user_id, max(reported_at) as at')->groupBy('user_id')->pluck('at', 'user_id'),
            Issue::query()->whereIn('reported_by', $ids)->selectRaw('reported_by as user_id, max(reported_at) as at')->groupBy('reported_by')->pluck('at', 'user_id'),
            SurveyResponse::query()->whereIn('collected_by', $ids)->selectRaw('collected_by as user_id, max(answered_at) as at')->groupBy('collected_by')->pluck('at', 'user_id'),
        ];
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, string> user id → engagement level
     */
    public function engagement(Collection $users): Collection
    {
        $since = now()->subDays((int) config('structure.engagement_days'));
        $output = $this->lastOutput($users->pluck('id'));

        return $users->mapWithKeys(fn (User $user) => [$user->id => match (true) {
            isset($output[$user->id]) && $output[$user->id]->gte($since) => self::ACTIVE,
            $user->last_seen_at !== null && $user->last_seen_at->gte($since) => self::OCCASIONAL,
            default => self::DORMANT,
        }]);
    }

    /**
     * Health of every ward the user can see.
     *
     * @return Collection<int, array{ward: Ward, coordinators: int, agents: int, active: int, last_meeting: ?Carbon, last_activity: ?Carbon, red: bool, reasons: list<string>}>
     */
    public function wardHealth(User $viewer, ?int $lgaId = null): Collection
    {
        $wards = Ward::query()->visibleTo($viewer)->with('lga')
            ->when($lgaId, fn ($query) => $query->where('lga_id', $lgaId))
            ->orderBy('name')->get();
        $ids = $wards->pluck('id')->all();

        $team = User::query()->whereIn('ward_id', $ids)->whereIn('role', [UserRole::WardCoordinator, UserRole::Agent])->whereNull('disabled_at')->get();
        $engagement = $this->engagement($team);

        $lastRegistration = Voter::query()->whereIn('ward_id', $ids)->selectRaw('ward_id, max(captured_at) as at')->groupBy('ward_id')->pluck('at', 'ward_id');
        $held = Event::query()->whereIn('ward_id', $ids)->where('status', Event::HELD)->where('starts_at', '<=', now());
        $lastEvent = (clone $held)->selectRaw('ward_id, max(starts_at) as at')->groupBy('ward_id')->pluck('at', 'ward_id');
        $lastMeeting = (clone $held)->where('type', 'meeting')->selectRaw('ward_id, max(starts_at) as at')->groupBy('ward_id')->pluck('at', 'ward_id');
        $quietSince = now()->subDays((int) config('structure.quiet_ward_days'));

        return $wards->map(function (Ward $ward) use ($team, $engagement, $lastRegistration, $lastEvent, $lastMeeting, $quietSince) {
            $members = $team->where('ward_id', $ward->id);
            $agents = $members->where('role', UserRole::Agent);
            $times = array_filter([$lastRegistration[$ward->id] ?? null, $lastEvent[$ward->id] ?? null]);
            $lastActivity = $times ? Carbon::parse(max($times)) : null;
            $coordinators = $members->where('role', UserRole::WardCoordinator)->count();

            $reasons = array_values(array_filter([
                $coordinators === 0 ? 'No coordinator' : null,
                $lastActivity === null ? 'No activity yet' : ($lastActivity->lt($quietSince) ? 'Quiet for '.(int) $lastActivity->diffInDays(now()).' days' : null),
            ]));

            return [
                'ward' => $ward,
                'coordinators' => $coordinators,
                'agents' => $agents->count(),
                'active' => $agents->filter(fn (User $agent) => $engagement[$agent->id] === self::ACTIVE)->count(),
                'last_meeting' => isset($lastMeeting[$ward->id]) ? Carbon::parse($lastMeeting[$ward->id]) : null,
                'last_activity' => $lastActivity,
                'red' => $reasons !== [],
                'reasons' => $reasons,
            ];
        })->values();
    }
}
