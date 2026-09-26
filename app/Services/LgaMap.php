<?php

namespace App\Services;

use App\Models\Lga;
use App\Models\User;

/**
 * A schematic map of Ebonyi's 13 LGAs (from Election Shield): one tile
 * each, placed roughly where the LGA lies (north at the top), so it reads
 * at phone width without boundary data. Each layer gives every tile a
 * colour step and a value; the value is always shown as text, so no
 * reading depends on colour.
 */
class LgaMap
{
    /** [column, row] on a 4 × 5 grid. */
    public const LAYOUT = [
        'Ishielu' => [1, 1], 'Ohaukwu' => [2, 1], 'Ebonyi' => [3, 1], 'Izzi' => [4, 1],
        'Ezza North' => [2, 2], 'Abakaliki' => [3, 2],
        'Ezza South' => [2, 3], 'Ikwo' => [3, 3],
        'Ohaozara' => [1, 4], 'Onicha' => [2, 4], 'Afikpo North' => [3, 4],
        'Ivo' => [1, 5], 'Afikpo South' => [3, 5],
    ];

    /**
     * @param  array<string, array{label: string, values: array<string, float|int|null>, format?: string}>  $layers  values keyed by LGA name
     * @return array{tiles: list<array{name: string, slug: ?string, col: int, row: int, allowed: bool, cells: array<string, array{step: int, value: string}>}>, layers: array<string, array{label: string, legend: list<array{0: int, 1: string}>}>}
     */
    public function build(User $user, array $layers): array
    {
        $lgas = Lga::query()->get()->keyBy('name');
        $tiles = [];

        foreach (self::LAYOUT as $name => [$col, $row]) {
            $lga = $lgas[$name] ?? null;
            $cells = [];

            foreach ($layers as $key => $layer) {
                $cells[$key] = $this->cell($layer['values'][$name] ?? null, $layer['values'], $layer['format'] ?? 'number');
            }

            $tiles[] = [
                'name' => $name,
                'slug' => $lga?->slug,
                'col' => $col,
                'row' => $row,
                'allowed' => $lga !== null && $user->canSeeLga($lga),
                'cells' => $cells,
            ];
        }

        $legends = [];
        foreach ($layers as $key => $layer) {
            $legends[$key] = ['label' => $layer['label'], 'legend' => $this->legend($layer['values'], $layer['format'] ?? 'number')];
        }

        return ['tiles' => $tiles, 'layers' => $legends];
    }

    /**
     * Five sequential steps from the smallest to the largest value (0 = no data).
     *
     * @param  array<string, float|int|null>  $values
     * @return array{step: int, value: string}
     */
    private function cell(float|int|null $value, array $values, string $format): array
    {
        if ($value === null) {
            return ['step' => 0, 'value' => '—'];
        }

        if ($value == 0) {
            return ['step' => 0, 'value' => $this->format(0, $format)];
        }

        $known = array_filter($values, fn ($v) => $v !== null);
        [$min, $max] = [min($known), max($known)];
        $step = $max > $min ? 1 + (int) floor(4 * ($value - $min) / ($max - $min)) : 3;

        return ['step' => $step, 'value' => $this->format($value, $format)];
    }

    /**
     * @param  array<string, float|int|null>  $values
     * @return list<array{0: int, 1: string}>
     */
    private function legend(array $values, string $format): array
    {
        $known = array_filter($values, fn ($v) => $v !== null);

        if ($known === []) {
            return [[0, 'No data']];
        }

        return [[1, $this->format(min($known), $format)], [5, $this->format(max($known), $format)]];
    }

    private function format(float|int $value, string $format): string
    {
        return match ($format) {
            'percent' => rtrim(rtrim(number_format($value, 1), '0'), '.').'%',
            'compact' => $value >= 1_000_000 ? round($value / 1_000_000, 1).'M' : ($value >= 1000 ? round($value / 1000).'k' : (string) round($value)),
            default => number_format($value),
        };
    }
}
