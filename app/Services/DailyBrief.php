<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\PastResult;
use App\Models\TaskReport;
use App\Models\User;
use App\Models\Voter;
use App\Support\Settings;
use App\Support\Time;
use Illuminate\Support\Collection;

/**
 * The daily dashboard, the leadership's home page and the printable
 * morning brief: where we are winning, where we are losing, what to push
 * next, field activity today, and the leaderboard highlights.
 */
class DailyBrief
{
    public function __construct(private Intelligence $intel, private Points $points, private Structure $structure) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $viewer): array
    {
        $wards = $this->intel->wards($viewer);
        $today = Time::now()->startOfDay()->utc();
        $weekStart = Points::weekStart();
        $voters = Voter::query()->counted()->inAreaOf($viewer);
        $thisWeek = (clone $voters)->where('captured_at', '>=', $weekStart)->count();
        $lastWeek = (clone $voters)->whereBetween('captured_at', [$weekStart->copy()->subWeek(), $weekStart])->count();
        $daily = (clone $voters)->where('captured_at', '>=', now()->subDays(14))->pluck('captured_at')
            ->countBy(fn ($at) => $at->copy()->setTimezone(Time::zone())->format('Y-m-d'));
        $health = $this->structure->wardHealth($viewer);

        return [
            'winning' => $wards->where('zone', 'stronghold')->sortByDesc(fn ($row) => [$row['trend'] ?? 0, $row['share']])->take(5)->values(),
            'losing' => $wards->filter(fn ($row) => $row['zone'] === 'weak' || ($row['zone'] === 'swing' && ($row['trend'] ?? 0) < 0))
                ->sortBy(fn ($row) => [$row['zone'] === 'weak' ? 0 : 1, $row['trend'] ?? 0, $row['share']])->take(5)->values(),
            'zones' => $wards->countBy('zone'),
            'priority' => $wards->sortByDesc('priority')->take(5)->values(),
            'suggestion' => $this->suggestion(),
            'rising' => $this->risingIssues($viewer),
            'field' => [
                'today' => (clone $voters)->where('captured_at', '>=', $today)->count(),
                'week' => $thisWeek,
                'weekDelta' => $lastWeek > 0 ? round(100 * ($thisWeek - $lastWeek) / $lastWeek, 1) : null,
                'total' => (clone $voters)->count(),
                'target' => Settings::int('target.total'),
                'spark' => collect(range(13, 0))->map(fn ($ago) => (int) ($daily[Time::now()->subDays($ago)->format('Y-m-d')] ?? 0))->all(),
                'tasksDone' => TaskReport::query()->where('done', true)->where('reported_at', '>=', $today)
                    ->whereHas('task', fn ($query) => $query->inAreaOf($viewer))->count(),
                'activeAgents' => (clone $voters)->where('captured_at', '>=', $today)->distinct()->count('captured_by'),
                'issues' => Issue::query()->inAreaOf($viewer)->where('reported_at', '>=', $today)->count(),
                'quietWards' => $health->filter(fn ($row) => in_array('No activity yet', $row['reasons'], true) || collect($row['reasons'])->contains(fn ($r) => str_starts_with($r, 'Quiet')))->count(),
                'wards' => $health->count(),
            ],
            'leaders' => [
                'lga' => $this->points->areas('lga', $weekStart)->first(fn ($row) => $row['points'] > 0),
                'ward' => $this->points->areas('ward', $weekStart)->first(fn ($row) => $row['points'] > 0),
                'agents' => $this->points->agents(null, null, $weekStart)->filter(fn ($row) => $row['points'] > 0)->take(3)->values(),
            ],
            'party' => Intelligence::party(),
            'hasResults' => PastResult::query()->exists(),
            'generatedAt' => Time::now(),
        ];
    }

    /**
     * The day's AI suggestion (phase 7 writes it each morning from the
     * aggregate numbers), clearly labelled as a suggestion.
     *
     * @return array{text: string, at: ?string}|null
     */
    public function suggestion(): ?array
    {
        $text = Settings::get('brief.suggestion');

        return filled($text) ? ['text' => (string) $text, 'at' => Settings::get('brief.suggestion_at')] : null;
    }

    /**
     * Issue categories with more reports this week than last.
     *
     * @return Collection<int, array{category: string, label: string, week: int, last: int}>
     */
    public function risingIssues(User $viewer): Collection
    {
        $weekStart = Points::weekStart();
        $rows = Issue::query()->inAreaOf($viewer)->where('status', '!=', 'rejected')->where('reported_at', '>=', $weekStart->copy()->subWeek())
            ->get(['category', 'reported_at'])->groupBy('category');

        return $rows->map(fn ($issues, $category) => [
            'category' => $category,
            'label' => config("field.issue_categories.{$category}", $category),
            'week' => $issues->where('reported_at', '>=', $weekStart)->count(),
            'last' => $issues->where('reported_at', '<', $weekStart)->count(),
        ])->filter(fn ($row) => $row['week'] > $row['last'])->sortByDesc(fn ($row) => $row['week'] - $row['last'])->take(4)->values();
    }

    /**
     * 7 AM: tell leadership the brief is ready, and each LGA's leaders how
     * many of their wards went quiet.
     */
    public function notify(): void
    {
        PushAlerts::queue('daily_brief', ['title' => 'Your daily brief is ready', 'body' => 'Where we’re winning, where we’re losing, and what to push today.', 'url' => '/brief', 'tag' => 'daily-brief']);

        $quiet = $this->structure->wardHealth(User::query()->where('role', 'admin')->first() ?? new User(['role' => 'admin']))
            ->filter(fn ($row) => $row['coordinators'] > 0 && collect($row['reasons'])->contains(fn ($r) => str_starts_with($r, 'Quiet')))
            ->groupBy(fn ($row) => $row['ward']->lga_id);

        foreach ($quiet as $lgaId => $rows) {
            PushAlerts::queue('ward_quiet', [
                'title' => $rows->count().' '.($rows->count() === 1 ? 'ward' : 'wards').' in '.$rows->first()['ward']->lga->name.' went quiet',
                'body' => $rows->take(3)->map(fn ($row) => $row['ward']->name)->implode(', ').': nothing registered or held for a week.',
                'url' => '/structure',
                'tag' => 'ward-quiet-'.$lgaId,
            ], (int) $lgaId);
        }

        PushAlerts::flush();
    }
}
