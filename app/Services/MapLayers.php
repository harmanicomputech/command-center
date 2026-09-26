<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\User;
use App\Models\Voter;
use App\Support\Aggregates;
use Illuminate\Support\Collection;

/**
 * The dashboard map's layers (zone, registrations, activity, issues) for
 * both maps: the LGA tile map, and the ward map when boundaries are loaded.
 * Every value is also shown as text, so no reading depends on colour.
 */
class MapLayers
{
    public const LABELS = ['zone' => 'Zone', 'registrations' => 'Registrations', 'activity' => 'Activity (7 days)', 'issues' => 'Open issues'];

    public function __construct(private Intelligence $intel, private LgaMap $lgaMap, private WardMap $wardMap) {}

    /**
     * @return array{lga: array<string, mixed>, ward: ?array<string, mixed>}
     */
    public function build(User $viewer): array
    {
        return Aggregates::remember('map.layers', $viewer, [], fn () => $this->compute($viewer));
    }

    private function compute(User $viewer): array
    {
        $lgaRows = $this->intel->lgas()->keyBy('name');
        $counts = fn (string $column, $query) => $query->selectRaw("{$column} as area, count(*) as n")->groupBy($column)->pluck('n', 'area');

        $perLga = [
            'registrations' => $counts('lga_id', Voter::query()->counted()),
            'activity' => $counts('lga_id', Voter::query()->counted()->where('captured_at', '>=', now()->subDays(7))),
            'issues' => $counts('lga_id', Issue::query()->whereNotIn('status', ['rejected', 'addressed'])),
        ];
        $lgaIds = $lgaRows->map(fn ($row) => $row['id']);

        $lgaLayers = ['zone' => [
            'label' => self::LABELS['zone'],
            'cells' => $lgaRows->map(fn ($row) => $this->zoneCell($row))->all(),
            'legend' => $this->zoneLegend(),
        ]];
        foreach ($perLga as $key => $values) {
            $lgaLayers[$key] = ['label' => self::LABELS[$key], 'values' => $lgaIds->map(fn ($id) => (int) ($values[$id] ?? 0))->all()];
        }

        return [
            'lga' => $this->lgaMap->build($viewer, $lgaLayers),
            'ward' => $this->wardLayers($viewer, $counts),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function wardLayers(User $viewer, callable $counts): ?array
    {
        $shapes = $this->wardMap->load();
        if ($shapes === null) {
            return null;
        }

        $rows = $this->intel->wards()->keyBy('id');
        $visible = $this->intel->wards($viewer)->pluck('id')->flip();
        $perWard = [
            'registrations' => $counts('ward_id', Voter::query()->counted()),
            'activity' => $counts('ward_id', Voter::query()->counted()->where('captured_at', '>=', now()->subDays(7))),
            'issues' => $counts('ward_id', Issue::query()->whereNotIn('status', ['rejected', 'addressed'])),
        ];
        $topIssue = Issue::query()->where('status', '!=', 'rejected')->selectRaw('ward_id, category, count(*) as n')->groupBy('ward_id', 'category')->get()
            ->groupBy('ward_id')->map(fn (Collection $group) => config('field.issue_categories.'.$group->sortByDesc('n')->first()->category));

        $wards = [];
        foreach ($shapes['wards'] as $wardId => $d) {
            $row = $rows[$wardId] ?? null;
            if (! $row) {
                continue;
            }
            $cells = ['zone' => $this->zoneCell($row)];
            foreach ($perWard as $key => $values) {
                $cells[$key] = ['value' => (int) ($values[$wardId] ?? 0)];
            }
            $wards[] = [
                'id' => $wardId,
                'd' => $d,
                'name' => $row['name'],
                'lga' => $row['lga'],
                'url' => $visible->has($wardId) ? route('areas.ward', [$row['lga_slug'], $row['slug']]) : null,
                'zone' => Intelligence::ZONES[$row['zone']]['label'],
                'share' => $row['share'],
                'top_issue' => $topIssue[$wardId] ?? null,
                'cells' => $cells,
            ];
        }

        // Sequential steps for the count layers.
        foreach (array_keys($perWard) as $key) {
            $max = max(1, ...array_map(fn ($ward) => $ward['cells'][$key]['value'], $wards ?: [['cells' => [$key => ['value' => 0]]]]));
            foreach ($wards as &$ward) {
                $value = $ward['cells'][$key]['value'];
                $ward['cells'][$key]['color'] = $value === 0 ? 'var(--seq-0)' : 'var(--seq-'.max(1, (int) ceil(5 * $value / $max)).')';
                $ward['cells'][$key]['text'] = number_format($value);
            }
            unset($ward);
        }

        return ['viewBox' => $shapes['viewBox'], 'attribution' => $shapes['attribution'], 'wards' => $wards, 'layers' => self::LABELS, 'legend' => $this->zoneLegend()];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{color: string, ink: string, value: string, text: string}
     */
    private function zoneCell(array $row): array
    {
        $zone = $row['zone'];

        return [
            'color' => Intelligence::ZONES[$zone]['color'],
            'ink' => $zone === 'unknown' ? 'var(--text)' : "var(--on-div-{$zone})",
            'value' => $row['share'] === null ? '—' : rtrim(rtrim(number_format($row['share'], 1), '0'), '.').'%',
            'text' => Intelligence::ZONES[$zone]['label'].($row['share'] === null ? '' : ' · '.$row['share'].'%'),
        ];
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function zoneLegend(): array
    {
        return collect(Intelligence::ZONES)->map(fn ($zone) => [$zone['color'], $zone['label']])->values()->all();
    }
}
