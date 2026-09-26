<?php

namespace App\Http\Controllers\Console;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Models\Voter;
use App\Services\Structure;
use App\Support\Time;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The structure as people: LGA leaders, coordinators and agents in the
 * user's area, with their engagement over the last 14 days.
 */
class PeopleController extends Controller
{
    public function index(Request $request, Structure $structure): View
    {
        $viewer = $request->user();
        $role = UserRole::tryFrom((string) $request->query('role'));
        $level = in_array($request->query('engagement'), array_keys(Structure::LABELS), true) ? $request->query('engagement') : null;
        $q = trim((string) $request->query('q'));

        $people = $this->scoped($viewer)
            ->with('ward.lga', 'lga')
            ->when($role, fn ($query) => $query->where('role', $role))
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderByRaw("case role when 'lga_leader' then 0 when 'ward_coordinator' then 1 else 2 end")->orderBy('name')
            ->get();

        $engagement = $structure->engagement($people);
        $counts = $engagement->countBy();
        $registrations = Voter::query()->counted()->whereIn('captured_by', $people->pluck('id'))
            ->selectRaw('captured_by, count(*) as n')->groupBy('captured_by')->pluck('n', 'captured_by');

        if ($level) {
            $people = $people->filter(fn (User $person) => $engagement[$person->id] === $level)->values();
        }

        return view('people.index', [
            'people' => $people,
            'engagement' => $engagement,
            'counts' => $counts,
            'registrations' => $registrations,
            'role' => $role,
            'level' => $level,
            'q' => $q,
        ]);
    }

    public function show(Request $request, User $person, Structure $structure): View
    {
        abort_unless($this->scoped($request->user())->whereKey($person->id)->exists(), 403, 'This person is outside your area.');

        $weekStart = Time::now()->startOfWeek()->utc();
        $mine = Voter::query()->counted()->where('captured_by', $person->id);

        return view('people.show', [
            'person' => $person->load('ward.lga', 'lga', 'inviter'),
            'level' => $structure->engagement(collect([$person]))[$person->id],
            'lastOutput' => $structure->lastOutput(collect([$person->id]))[$person->id] ?? null,
            'stats' => [
                'total' => (clone $mine)->count(),
                'week' => (clone $mine)->where('captured_at', '>=', $weekStart)->count(),
                'verified' => (clone $mine)->where('status', Voter::VERIFIED)->count(),
                'invalid' => Voter::query()->where('captured_by', $person->id)->where('status', Voter::INVALID)->count(),
                'events' => $person->belongsToMany(Event::class)->where('status', Event::HELD)->count(),
            ],
            'daily' => $this->daily($person),
        ]);
    }

    /**
     * Registrations per day for the last 14 days, oldest first.
     *
     * @return list<int>
     */
    private function daily(User $person): array
    {
        $days = Voter::query()->counted()->where('captured_by', $person->id)->where('captured_at', '>=', now()->subDays(14))
            ->pluck('captured_at')->countBy(fn ($at) => $at->copy()->setTimezone(Time::zone())->format('Y-m-d'));

        return collect(range(13, 0))->map(fn (int $ago) => (int) ($days[Time::now()->subDays($ago)->format('Y-m-d')] ?? 0))->all();
    }

    private function scoped(User $viewer)
    {
        $query = User::query()->whereIn('role', [UserRole::LgaLeader, UserRole::WardCoordinator, UserRole::Agent])->whereNull('disabled_at');

        return match (true) {
            $viewer->role->isStatewide() => $query,
            $viewer->role === UserRole::LgaLeader => $query->where('lga_id', $viewer->lga_id),
            default => $viewer->limitToArea($query, 'users.ward_id'),
        };
    }
}
