<?php

namespace App\Services;

use App\Models\Lga;
use App\Models\PastResult;
use App\Models\User;
use App\Models\Voter;
use App\Models\Ward;
use App\Support\Settings;
use Illuminate\Support\Collection;

/**
 * Zone classification and priority, per ward and per LGA.
 *
 * "Our share" blends up to three sources, weighted in Settings (a source
 * with no data drops out and the rest are re-weighted):
 *
 *  - past results: our party's share of the latest imported governorship
 *    election (ward figures, or the LGA's where a ward has none);
 *  - canvassing: the average support of voters registered by the field
 *    (strong 1, leaning 0.75, undecided 0.5, leaning opponent 0.25,
 *    opponent 0), used once the sample is big enough;
 *  - surveys: the average of voting-intention answers (same scale),
 *    used once the sample is big enough.
 *
 * Zones: stronghold from 55%, swing from 40%, weak below (Settings);
 * unknown with no source. Priority = registered voters × (1 − certainty) ×
 * reachability: big, uncertain, reachable wards first.
 */
class Intelligence
{
    public const ZONES = [
        'stronghold' => ['label' => 'Stronghold', 'tone' => 'good', 'color' => 'var(--div-strong)'],
        'swing' => ['label' => 'Swing', 'tone' => 'warn', 'color' => 'var(--div-swing)'],
        'weak' => ['label' => 'Weak', 'tone' => 'bad', 'color' => 'var(--div-weak)'],
        'unknown' => ['label' => 'Unknown', 'tone' => null, 'color' => 'var(--div-unknown)'],
    ];

    /** Default presets the strategists can change (Settings → LGA presets). */
    public const DEFAULT_TAGS = [
        'izzi' => 'Priority mobilisation',
        'ikwo' => 'Priority mobilisation',
        'abakaliki' => 'Urban: digital and media',
    ];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $memo = [];

    /**
     * @return Collection<int, array{id: int, name: string, lga: string, lga_slug: string, slug: string, registered: int, share: ?float, zone: string, sources: list<string>, basis: string, canvassed: int, canvass_share: ?float, results_share: ?float, results_year: ?int, certainty: float, reach: float, priority: int, trend: ?float, tag: ?string}>
     */
    public function wards(?User $viewer = null, ?int $lgaId = null): Collection
    {
        $all = collect($this->memo['wards'] ??= $this->computeWards());

        return $all->filter(function ($row) use ($viewer, $lgaId) {
            return ($lgaId === null || $row['lga_id'] === $lgaId) && ($viewer === null || $viewer->role->isStatewide() || in_array($row['id'], $this->visibleWardIds($viewer), true));
        })->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function lgas(?User $viewer = null): Collection
    {
        $all = collect($this->memo['lgas'] ??= $this->computeLgas());

        return $all->filter(fn ($row) => $viewer === null || $viewer->role->isStatewide() || in_array($row['id'], Lga::query()->visibleTo($viewer)->pluck('id')->all(), true))->values();
    }

    public static function reach(string $lgaSlug): float
    {
        return max(0.1, min(1.0, (float) (Settings::get("intel.reach.{$lgaSlug}") ?? 1.0)));
    }

    public static function tag(string $lgaSlug): ?string
    {
        $tag = Settings::get("intel.tag.{$lgaSlug}");

        return $tag === null ? (self::DEFAULT_TAGS[$lgaSlug] ?? null) : ($tag !== '' ? $tag : null);
    }

    public static function party(): ?string
    {
        $party = strtoupper(trim((string) Settings::get('campaign.party')));

        return $party !== '' ? $party : null;
    }

    /**
     * @return list<int>
     */
    private function visibleWardIds(User $viewer): array
    {
        return $this->memo['visible'][$viewer->id] ??= Ward::query()->visibleTo($viewer)->pluck('id')->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function computeWards(): array
    {
        $wards = Ward::query()->with('lga')->orderBy('name')->get();
        $canvass = $this->canvass('ward_id');
        $results = $this->results();
        $surveys = $this->memo['surveys'] ??= SurveyResults::intentionShares();

        return $wards->map(function (Ward $ward) use ($canvass, $results, $surveys) {
            $wardResult = $results['ward'][$ward->id] ?? null;
            $lgaResult = $results['lga'][$ward->lga_id] ?? null;

            return $this->row(
                ['id' => $ward->id, 'name' => $ward->name, 'slug' => $ward->slug, 'lga_id' => $ward->lga_id, 'lga' => $ward->lga->name, 'lga_slug' => $ward->lga->slug, 'registered' => $ward->registered_voters],
                $canvass[$ward->id] ?? null,
                $wardResult ?? $lgaResult,
                $wardResult === null && $lgaResult !== null,
                $ward->lga->slug,
                $surveys['ward'][$ward->id] ?? null,
            );
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function computeLgas(): array
    {
        $canvass = $this->canvass('lga_id');
        $results = $this->results();
        $surveys = $this->memo['surveys'] ??= SurveyResults::intentionShares();

        return Lga::query()->orderBy('name')->get()->map(fn (Lga $lga) => $this->row(
            ['id' => $lga->id, 'name' => $lga->name, 'slug' => $lga->slug, 'lga_id' => $lga->id, 'lga' => $lga->name, 'lga_slug' => $lga->slug, 'registered' => $lga->registered_voters],
            $canvass[$lga->id] ?? null,
            $results['lga'][$lga->id] ?? null,
            false,
            $lga->slug,
            $surveys['lga'][$lga->id] ?? null,
        ))->all();
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array{n: int, share: float, n_before: int, share_before: ?float}|null  $canvass
     * @param  array{share: float, year: int}|null  $result
     * @return array<string, mixed>
     */
    private function row(array $base, ?array $canvass, ?array $result, bool $resultIsLga, string $lgaSlug, ?array $survey = null): array
    {
        $minSample = max(1, Settings::int('intel.min_sample'));
        $weights = ['results' => Settings::int('intel.weight_results'), 'canvass' => Settings::int('intel.weight_canvass'), 'survey' => Settings::int('intel.weight_survey')];
        $parts = [];
        $confidence = [];

        if ($result !== null) {
            $parts['results'] = $result['share'];
            $confidence[] = $resultIsLga ? 0.35 : 0.6;
        }
        if ($canvass !== null && $canvass['n'] >= $minSample) {
            $parts['canvass'] = $canvass['share'];
            $confidence[] = min(0.6, $canvass['n'] / 500);
        }
        if ($survey !== null && $survey['n'] >= $minSample) {
            $parts['survey'] = $survey['share'];
            $confidence[] = min(0.5, $survey['n'] / 400);
        }

        $totalWeight = array_sum(array_intersect_key($weights, $parts));
        $share = $parts === [] ? null : ($totalWeight > 0
            ? array_sum(array_map(fn ($key) => $parts[$key] * $weights[$key], array_keys($parts))) / $totalWeight
            : array_sum($parts) / count($parts));
        $conf = 1 - array_product(array_map(fn ($c) => 1 - $c, $confidence ?: [0]));
        $certainty = $share === null ? 0.0 : round($conf * min(1, abs($share - 50) / 25), 3);
        $reach = self::reach($lgaSlug);

        $basis = [];
        if (isset($parts['canvass'])) {
            $basis[] = number_format($canvass['n']).' canvassed voters';
        } elseif (($canvass['n'] ?? 0) > 0) {
            $basis[] = 'only '.number_format($canvass['n']).' canvassed (under '.$minSample.', not used)';
        }
        if (isset($parts['survey'])) {
            $basis[] = number_format($survey['n']).' survey responses';
        }
        if ($result !== null) {
            $basis[] = 'the '.$result['year'].' result'.($resultIsLga ? ' (LGA figure)' : '');
        }

        $trend = null;
        if ($canvass !== null && $canvass['share_before'] !== null && $canvass['n_before'] >= $minSample && max(5, intdiv($minSample, 3)) <= $canvass['n'] - $canvass['n_before']) {
            $trend = round($canvass['share'] - $canvass['share_before'], 1);
        }

        return [
            ...$base,
            'share' => $share === null ? null : round($share, 1),
            'zone' => $this->zone($share),
            'sources' => array_keys($parts),
            'basis' => $basis === [] ? 'No data yet' : 'Based on '.(count($basis) > 2 ? implode(', ', array_slice($basis, 0, -1)).' and '.end($basis) : implode(' and ', $basis)),
            'canvassed' => (int) ($canvass['n'] ?? 0),
            'canvass_share' => isset($canvass['share']) ? round($canvass['share'], 1) : null,
            'results_share' => $result['share'] ?? null,
            'results_year' => $result['year'] ?? null,
            'certainty' => $certainty,
            'reach' => $reach,
            'priority' => (int) round($base['registered'] * (1 - $certainty) * $reach),
            'trend' => $trend,
            'tag' => self::tag($lgaSlug),
        ];
    }

    private function zone(?float $share): string
    {
        return match (true) {
            $share === null => 'unknown',
            $share >= Settings::int('intel.stronghold') => 'stronghold',
            $share >= Settings::int('intel.swing') => 'swing',
            default => 'weak',
        };
    }

    /**
     * Canvass support per ward or LGA: now, and before this week (the trend).
     *
     * @return array<int, array{n: int, share: float, n_before: int, share_before: ?float}>
     */
    private function canvass(string $column): array
    {
        $weight = 'case support_level '.collect(config('canvass.support_levels'))->map(fn ($level, $key) => "when '{$key}' then {$level['weight']}")->implode(' ').' else 0.5 end';
        $weekStart = Points::weekStart();

        return Voter::query()->counted()->whereNull('erased_at')
            ->selectRaw("{$column} as area, count(*) as n, avg({$weight}) as share, sum(case when captured_at < ? then 1 else 0 end) as n_before, sum(case when captured_at < ? then {$weight} else 0 end) as weight_before", [$weekStart, $weekStart])
            ->groupBy($column)->get()
            ->mapWithKeys(fn ($row) => [(int) $row->area => [
                'n' => (int) $row->n,
                'share' => 100 * (float) $row->share,
                'n_before' => (int) $row->n_before,
                'share_before' => $row->n_before > 0 ? 100 * (float) $row->weight_before / (int) $row->n_before : null,
            ]])->all();
    }

    /**
     * Our party's share in the latest year with results: per ward (ward rows)
     * and per LGA (LGA rows, or the sum of its ward rows).
     *
     * @return array{ward: array<int, array{share: float, year: int}>, lga: array<int, array{share: float, year: int}>}
     */
    private function results(): array
    {
        $party = self::party();
        $year = PastResult::query()->max('year');

        if ($party === null || $year === null) {
            return ['ward' => [], 'lga' => []];
        }

        $rows = PastResult::query()->where('year', $year)->get();
        $share = fn (Collection $group) => ($total = $group->sum('votes')) > 0 ? 100 * $group->where('party', $party)->sum('votes') / $total : null;

        $wards = $rows->whereNotNull('ward_id')->groupBy('ward_id')->map($share)->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => ['share' => round($value, 1), 'year' => (int) $year]);

        $lgas = $rows->groupBy('lga_id')->map(function (Collection $group) use ($share) {
            $lgaRows = $group->whereNull('ward_id');

            return $share($lgaRows->isNotEmpty() ? $lgaRows : $group);
        })->filter(fn ($value) => $value !== null)->map(fn ($value) => ['share' => round($value, 1), 'year' => (int) $year]);

        return ['ward' => $wards->all(), 'lga' => $lgas->all()];
    }
}
