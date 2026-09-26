<?php

namespace App\Services;

use App\Models\Lga;
use App\Models\User;
use App\Models\Voter;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Builder;

/**
 * Segments of canvassed voters by age band, occupation, gender, area,
 * support level and top issue. Only counts leave this class: a segment is
 * a description of a group, never a list of people.
 */
class Segments
{
    public const DIMENSIONS = [
        'age_band' => ['Age', 'canvass.age_bands'],
        'occupation' => ['Occupation', 'canvass.occupations'],
        'gender' => ['Gender', 'canvass.genders'],
        'support_level' => ['Support', 'canvass.support_levels'],
        'top_issue' => ['Top issue', 'canvass.issues'],
    ];

    /**
     * Keep only known filter values.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, list<string|int>>
     */
    public function clean(array $input): array
    {
        $filters = [];

        foreach (self::DIMENSIONS as $key => [, $config]) {
            $values = array_values(array_intersect((array) ($input[$key] ?? []), array_map('strval', array_keys(config($config)))));
            $values && $filters[$key] = $values;
        }

        foreach (['lga_id', 'ward_id'] as $key) {
            $ids = array_values(array_filter(array_map('intval', (array) ($input[$key] ?? []))));
            $ids && $filters[$key] = $ids;
        }

        return $filters;
    }

    /**
     * @param  array<string, list<string|int>>  $filters
     */
    public function query(User $viewer, array $filters): Builder
    {
        $query = Voter::query()->counted()->whereNull('erased_at')->inAreaOf($viewer);

        foreach ($filters as $key => $values) {
            $query->whereIn("voters.{$key}", $values);
        }

        return $query;
    }

    /**
     * @param  array<string, list<string|int>>  $filters
     * @return array{count: int, with_phone: int, breakdowns: array<string, array<string, int>>, lgas: array<string, int>}
     */
    public function describe(User $viewer, array $filters): array
    {
        $query = $this->query($viewer, $filters);
        $breakdowns = [];

        foreach (array_keys(self::DIMENSIONS) as $key) {
            $breakdowns[$key] = (clone $query)->whereNotNull($key)->selectRaw("{$key} as k, count(*) as n")->groupBy($key)->pluck('n', 'k')->map(fn ($n) => (int) $n)->all();
        }

        return [
            'count' => (clone $query)->count(),
            'with_phone' => (clone $query)->whereNotNull('phone_hash')->whereNull('opted_out_at')->count(),
            'breakdowns' => $breakdowns,
            'lgas' => (clone $query)->join('lgas', 'lgas.id', '=', 'voters.lga_id')->selectRaw('lgas.name, count(*) as n')->groupBy('lgas.name')->orderByDesc('n')->pluck('n', 'name')->map(fn ($n) => (int) $n)->all(),
        ];
    }

    /**
     * "Farmers in Izzi and Ikwo, 25–44": a plain description for prompts
     * and screens (no personal data).
     *
     * @param  array<string, list<string|int>>  $filters
     */
    public function label(array $filters): string
    {
        $parts = [];
        foreach (self::DIMENSIONS as $key => [$title, $config]) {
            if (! empty($filters[$key])) {
                $labels = array_map(fn ($value) => ($label = config("{$config}.{$value}")) && is_array($label) ? $label['label'] : ($label ?? $value), $filters[$key]);
                $parts[] = $title.': '.implode(', ', $labels);
            }
        }
        if (! empty($filters['lga_id'])) {
            $parts[] = 'LGA: '.Lga::query()->whereIn('id', $filters['lga_id'])->orderBy('name')->pluck('name')->implode(', ');
        }
        if (! empty($filters['ward_id'])) {
            $parts[] = 'Ward: '.Ward::query()->whereIn('id', $filters['ward_id'])->orderBy('name')->pluck('name')->implode(', ');
        }

        return $parts ? implode(' · ', $parts) : 'Everyone canvassed';
    }
}
