<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\NarrativeReport;
use App\Models\PollingUnit;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\Task;
use App\Models\Voter;
use App\Models\Ward;
use App\Services\Badges;
use App\Services\Field\AgentStats;
use App\Services\Field\RegisterVoter;
use App\Services\Points;
use App\Support\Settings;
use App\Support\Time;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The Field Force app (phone). Forms save to the outbox on the phone first
 * (public/outbox.js) and reach the server through /api/field/sync; the
 * POST routes here are the fallback when scripts don't run.
 */
class FieldController extends Controller
{
    public function home(Request $request, AgentStats $stats): View
    {
        $user = $request->user();

        return view('field.home', [
            'user' => $user,
            'greeting' => Time::greeting(),
            'stats' => $stats->for($user),
            'target' => Settings::int('target.agent_daily'),
            'recent' => Voter::query()->where('captured_by', $user->id)->latest('captured_at')->limit(3)->get(),
            'surveys' => Survey::liveInWard($user->ward),
            'tasks' => Task::query()->for($user)->where('status', Task::OPEN)
                ->with(['reports' => fn ($query) => $query->where('user_id', $user->id)])
                ->orderByRaw('due_on is null')->orderBy('due_on')->get()
                ->reject(fn (Task $task) => $task->doneBy($user))->take(3)->values(),
        ]);
    }

    public function register(Request $request): View
    {
        $user = $request->user();
        $lgaId = $user->lga_id ?? $user->ward?->lga_id;

        // The agent's LGA (voters near a ward boundary); staff their area.
        $wards = Ward::query()
            ->when($lgaId && ! $user->role->isStatewide(), fn ($query) => $query->where('lga_id', $lgaId))
            ->with('lga')->orderBy('name')->get();

        $units = PollingUnit::query()->whereIn('ward_id', $wards->pluck('id'))->orderBy('code')->get(['id', 'ward_id', 'code', 'name'])
            ->groupBy('ward_id')
            ->map(fn ($units) => $units->map(fn (PollingUnit $unit) => ['id' => $unit->id, 'name' => ($unit->name ?? $unit->inecCode()).' · '.substr($unit->code, -3)])->values());

        return view('field.register', [
            'wards' => $wards->map(fn (Ward $ward) => ['id' => $ward->id, 'name' => $user->role->isStatewide() ? $ward->fullName() : $ward->name])->values()->all(),
            'units' => $units->all(),
            'defaultWard' => $user->ward_id ?? $wards->first()?->id,
            'points' => ['unverified' => Settings::int('points.registration_unverified'), 'verified' => Settings::int('points.registration_verified')],
        ]);
    }

    /**
     * Without scripts the form posts here; the same handler as the sync.
     */
    public function store(Request $request, RegisterVoter $handler): RedirectResponse
    {
        $uuid = Str::isUuid((string) $request->input('uuid')) ? strtolower($request->input('uuid')) : (string) Str::uuid();
        $handler->apply($request->user(), $uuid, [...$request->except('_token', 'uuid'), 'consent' => $request->boolean('consent')]);

        return redirect()->route('field.register')->with('status', 'Saved. '.$request->input('name').' is registered.');
    }

    public function outbox(): View
    {
        return view('field.outbox');
    }

    /**
     * The agent's own registrations. Phone numbers are masked once synced.
     */
    public function registrations(Request $request): View
    {
        return view('field.registrations', [
            'voters' => Voter::query()->where('captured_by', $request->user()->id)->with('ward')->latest('captured_at')->paginate(30),
        ]);
    }

    public function me(Request $request, AgentStats $stats): View
    {
        return view('field.me', ['user' => $request->user(), 'stats' => $stats->for($request->user())]);
    }

    /**
     * "My tasks": mine and my ward's, open first. Kept on the phone by the
     * service worker, so it opens without network.
     */
    public function tasks(Request $request): View
    {
        $user = $request->user();

        return view('field.tasks', [
            'user' => $user,
            'surveys' => Survey::liveInWard($user->ward),
            'tasks' => Task::query()->for($user)->with(['reports' => fn ($query) => $query->where('user_id', $user->id)])
                ->where(fn ($query) => $query->where('status', Task::OPEN)->orWhere('updated_at', '>=', now()->subDays(14)))
                ->orderByRaw("status = 'closed'")->orderByRaw('due_on is null')->orderBy('due_on')->get(),
        ]);
    }

    public function task(Request $request, Task $task): View
    {
        $user = $request->user();
        abort_unless(Task::query()->for($user)->whereKey($task->id)->exists(), 404);
        $task->load(['reports' => fn ($query) => $query->where('user_id', $user->id)->with('photos'), 'author']);

        return view('field.task', ['task' => $task, 'user' => $user]);
    }

    public function issues(Request $request): View
    {
        $user = $request->user();

        return view('field.issues', [
            'mine' => Issue::query()->where('reported_by', $user->id)->with('photos')->latest('reported_at')->limit(10)->get(),
            ...$this->wardChoices($user),
        ]);
    }

    public function narratives(Request $request): View
    {
        $user = $request->user();

        return view('field.narratives', [
            'mine' => NarrativeReport::query()->where('reported_by', $user->id)->with('photos')->latest('seen_at')->limit(10)->get(),
            ...$this->wardChoices($user),
        ]);
    }

    public function leaderboard(Request $request, Points $points, Badges $badges): View
    {
        $user = $request->user();
        $period = $request->query('period') === 'all' ? 'all' : 'week';
        $scope = $request->query('scope') === 'lga' ? 'lga' : 'ward';
        $since = $period === 'week' ? Points::weekStart() : null;

        $rows = $scope === 'lga'
            ? $points->agents(null, $user->lga_id ?? $user->ward?->lga_id, $since)
            : $points->agents($user->ward_id, null, $since);

        return view('field.leaderboard', [
            'rows' => $rows,
            'me' => $rows->first(fn ($row) => $row['user']->id === $user->id),
            'period' => $period,
            'scope' => $scope,
            'user' => $user,
            'badges' => $badges->for($user),
        ]);
    }

    /**
     * Live surveys for the agent's ward, with the ward's progress against
     * the quota (full surveys drop off the list).
     */
    public function surveys(Request $request): View
    {
        $user = $request->user();
        $surveys = Survey::liveInWard($user->ward);
        $counts = SurveyResponse::query()->whereIn('survey_id', $surveys->pluck('id'))->where('ward_id', $user->ward_id)
            ->selectRaw('survey_id, count(*) as n')->groupBy('survey_id')->pluck('n', 'survey_id');
        $mine = SurveyResponse::query()->whereIn('survey_id', $surveys->pluck('id'))->where('collected_by', $user->id)
            ->selectRaw('survey_id, count(*) as n')->groupBy('survey_id')->pluck('n', 'survey_id');

        return view('field.surveys', ['surveys' => $surveys, 'counts' => $counts, 'mine' => $mine]);
    }

    public function survey(Request $request, Survey $survey): View
    {
        $user = $request->user();
        abort_unless(Survey::liveInWard($user->ward)->contains('id', $survey->id), 404);

        return view('field.survey', ['survey' => $survey->load('questions'), 'user' => $user]);
    }

    /**
     * The wards an agent may report in (their LGA), their own first.
     *
     * @return array{wards: array<int, string>, defaultWard: ?int}
     */
    private function wardChoices($user): array
    {
        $lgaId = $user->lga_id ?? $user->ward?->lga_id;
        $wards = Ward::query()->when($lgaId && ! $user->role->isStatewide(), fn ($query) => $query->where('lga_id', $lgaId))->with('lga')->orderBy('name')->get();

        return [
            'wards' => $wards->mapWithKeys(fn (Ward $ward) => [$ward->id => $user->role->isStatewide() ? $ward->fullName() : $ward->name])->all(),
            'defaultWard' => $user->ward_id ?? $wards->first()?->id,
        ];
    }
}
