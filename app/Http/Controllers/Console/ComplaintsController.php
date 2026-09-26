<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\Lga;
use App\Models\NarrativeReport;
use App\Services\LgaMap;
use App\Services\Points;
use App\Support\Time;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The complaints dashboard: field issue reports plus negative narrative
 * reports, by theme and LGA, and whether each theme is rising.
 */
class ComplaintsController extends Controller
{
    /** Issue categories and narrative topics folded into shared themes. */
    private const THEMES = [
        'road' => 'roads', 'roads' => 'roads', 'water' => 'water', 'electricity' => 'electricity', 'health' => 'health',
        'school' => 'education', 'education' => 'education', 'security' => 'security', 'flooding' => 'roads',
        'market' => 'markets', 'markets' => 'markets', 'jobs' => 'jobs', 'agriculture' => 'agriculture',
    ];

    private const LABELS = [
        'roads' => 'Roads and erosion', 'water' => 'Water', 'electricity' => 'Electricity', 'health' => 'Health',
        'education' => 'Schools', 'security' => 'Security', 'markets' => 'Markets', 'jobs' => 'Jobs',
        'agriculture' => 'Agriculture', 'other' => 'Other',
    ];

    public function index(Request $request, LgaMap $maps): View
    {
        $user = $request->user();
        $weekStart = Points::weekStart();
        $since = $weekStart->copy()->subWeeks(7);

        $rows = $this->rows($user, $since);
        $themes = $rows->groupBy('theme')->map(fn (Collection $items, $theme) => [
            'theme' => $theme,
            'label' => self::LABELS[$theme] ?? ucfirst($theme),
            'total' => $items->count(),
            'issues' => $items->where('kind', 'issue')->count(),
            'narratives' => $items->where('kind', 'narrative')->count(),
            'week' => $items->where('at', '>=', $weekStart)->count(),
            'last' => $items->whereBetween('at', [$weekStart->copy()->subWeek(), $weekStart])->count(),
            'spark' => collect(range(7, 0))->map(fn ($ago) => $items->whereBetween('at', [$weekStart->copy()->subWeeks($ago), $weekStart->copy()->subWeeks($ago - 1)])->count())->all(),
        ])->sortByDesc('total')->values();

        $theme = $request->query('theme');
        $filtered = $theme ? $rows->where('theme', $theme) : $rows;
        $byLga = $filtered->groupBy('lga_id')->map->count();

        return view('complaints.index', [
            'themes' => $themes,
            'rising' => $themes->filter(fn ($row) => $row['week'] > $row['last'] && $row['week'] >= 2)->sortByDesc(fn ($row) => $row['week'] - $row['last'])->values(),
            'theme' => $theme,
            'themeLabel' => $theme ? (self::LABELS[$theme] ?? $theme) : null,
            'map' => $maps->build($user, [
                'complaints' => ['label' => 'Complaints', 'values' => Lga::query()->get(['id', 'name'])->mapWithKeys(fn ($lga) => [$lga->name => (int) ($byLga[$lga->id] ?? 0)])->all()],
            ]),
            'lgas' => Lga::query()->whereIn('id', $byLga->keys())->get(['id', 'name'])->map(fn ($lga) => ['name' => $lga->name, 'n' => $byLga[$lga->id]])->sortByDesc('n')->values(),
            'total' => $rows->count(),
            'weekTotal' => $rows->where('at', '>=', $weekStart)->count(),
            'lastTotal' => $rows->whereBetween('at', [$weekStart->copy()->subWeek(), $weekStart])->count(),
            'since' => $since->copy()->setTimezone(Time::zone()),
        ]);
    }

    /**
     * @return Collection<int, array{kind: string, theme: string, lga_id: ?int, at: Carbon}>
     */
    private function rows($user, $since): Collection
    {
        $issues = Issue::query()->inAreaOf($user)->where('status', '!=', 'rejected')->where('reported_at', '>=', $since)
            ->get(['category', 'lga_id', 'reported_at'])
            ->map(fn ($issue) => ['kind' => 'issue', 'theme' => self::THEMES[$issue->category] ?? 'other', 'lga_id' => $issue->lga_id, 'at' => $issue->reported_at]);

        $narratives = NarrativeReport::query()->visibleTo($user)->where('tone', 'negative')->where('seen_at', '>=', $since)
            ->whereNotIn('topic', ['candidate', 'opponent', 'party', 'election'])
            ->get(['topic', 'lga_id', 'seen_at'])
            ->map(fn ($report) => ['kind' => 'narrative', 'theme' => self::THEMES[$report->topic] ?? 'other', 'lga_id' => $report->lga_id, 'at' => $report->seen_at]);

        return $issues->concat($narratives)->values();
    }
}
