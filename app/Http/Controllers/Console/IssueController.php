<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\Lga;
use App\Services\LgaMap;
use App\Support\Audit;
use App\Support\Time;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Community issues from the field: the map, the list, the trend and the
 * top issues per LGA (for speeches), with a status as they are used.
 */
class IssueController extends Controller
{
    public function index(Request $request, LgaMap $map): View
    {
        $user = $request->user();
        $category = array_key_exists((string) $request->query('category'), config('field.issue_categories')) ? $request->query('category') : null;
        $status = array_key_exists((string) $request->query('status'), config('field.issue_statuses')) ? $request->query('status') : null;
        $lga = $request->filled('lga') ? Lga::query()->visibleTo($user)->where('slug', $request->query('lga'))->first() : null;

        $scoped = fn () => Issue::query()->inAreaOf($user)->where('status', '!=', 'rejected')
            ->when($lga, fn (Builder $query) => $query->where('issues.lga_id', $lga->id));

        $issues = Issue::query()->inAreaOf($user)->with('ward.lga', 'reporter', 'photos')
            ->when($category, fn (Builder $query) => $query->where('category', $category))
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($lga, fn (Builder $query) => $query->where('issues.lga_id', $lga->id))
            ->latest('reported_at')->paginate(20)->withQueryString();

        $perLga = Issue::query()->inAreaOf($user)->where('status', '!=', 'rejected')
            ->join('lgas', 'lgas.id', '=', 'issues.lga_id')
            ->selectRaw('lgas.name, count(*) as n')->groupBy('lgas.name')->pluck('n', 'name');

        return view('issues.index', [
            'issues' => $issues,
            'category' => $category,
            'status' => $status,
            'lga' => $lga,
            'lgas' => Lga::query()->visibleTo($user)->orderBy('name')->get(),
            'byCategory' => $scoped()->selectRaw('category, count(*) as n, sum(coalesce(people_affected, 0)) as people')->groupBy('category')->orderByDesc('n')->get(),
            'weekly' => $this->weekly($scoped()),
            'map' => $map->build($user, [
                'issues' => ['label' => 'Issues reported', 'values' => collect(config('campaign.lgas'))->mapWithKeys(fn ($name) => [$name => (int) ($perLga[$name] ?? 0)])->all()],
            ]),
            'total' => $scoped()->count(),
        ]);
    }

    public function status(Request $request, Issue $issue): RedirectResponse
    {
        abort_unless($request->user()->canSeeWard($issue->ward), 403);
        $status = $request->validate(['status' => ['required', Rule::in(array_keys(config('field.issue_statuses')))]])['status'];

        $issue->update(['status' => $status, 'status_by' => $request->user()->id, 'status_at' => now()]);
        Audit::record('issues.status', "Marked issue #{$issue->id} “{$issue->statusLabel()}”", ['issue_id' => $issue->id]);

        return back()->with('status', 'Issue marked “'.$issue->statusLabel().'”.');
    }

    /**
     * The briefing pack: top 10 issues per LGA, printable (for speeches and
     * the candidate's solution-driven positioning).
     */
    public function brief(Request $request): View
    {
        $user = $request->user();
        $rows = Issue::query()->inAreaOf($user)->where('status', '!=', 'rejected')->with('lga')
            ->selectRaw('lga_id, category, count(*) as n, sum(coalesce(people_affected, 0)) as people, sum(case when severity in (\'high\', \'critical\') then 1 else 0 end) as serious')
            ->groupBy('lga_id', 'category')->get();

        $examples = Issue::query()->inAreaOf($user)->where('status', '!=', 'rejected')->with('ward')
            ->orderByRaw("case severity when 'critical' then 0 when 'high' then 1 when 'medium' then 2 else 3 end")->latest('reported_at')
            ->get()->groupBy(fn (Issue $issue) => $issue->lga_id.'|'.$issue->category)->map->take(2);

        Audit::record('issues.brief', 'Opened the issues briefing pack', rows: $rows->sum('n'));

        return view('issues.brief', [
            'lgas' => $rows->groupBy('lga_id')->map(fn ($issues) => ['lga' => $issues->first()->lga, 'top' => $issues->sortByDesc('n')->take(10)->values(), 'total' => $issues->sum('n')])->sortBy(fn ($row) => $row['lga']->name)->values(),
            'examples' => $examples,
            'generatedAt' => Time::now(),
        ]);
    }

    /**
     * Issues per week for the last 8 weeks, oldest first.
     *
     * @return list<int>
     */
    private function weekly(Builder $query): array
    {
        $start = Time::now()->startOfWeek()->subWeeks(7);
        $day = Time::sqlLocalDate('issues.reported_at');
        $weeks = $query->where('reported_at', '>=', $start->copy()->utc())->selectRaw("{$day} as d, count(*) as n")->groupByRaw($day)->pluck('n', 'd')
            ->reduce(function ($weeks, $n, $date) {
                $week = Carbon::parse($date)->startOfWeek()->format('Y-m-d');
                $weeks[$week] = ($weeks[$week] ?? 0) + (int) $n;

                return $weeks;
            }, []);

        return collect(range(0, 7))->map(fn ($i) => (int) ($weeks[$start->copy()->addWeeks($i)->format('Y-m-d')] ?? 0))->all();
    }
}
