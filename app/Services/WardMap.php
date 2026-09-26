<?php

namespace App\Services;

use App\Models\Lga;
use App\Models\Ward;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The real ward map: an uploaded ward-boundary GeoJSON (e.g. GRID3 Nigeria
 * wards, CC BY 4.0) matched to the register's wards, simplified and
 * projected once into SVG paths kept on the private disk. Rendered as an
 * SVG choropleth with no map tiles, so it works offline and prints well.
 */
class WardMap
{
    public const PATH = 'maps/wards.json';

    private const WIDTH = 1000;

    private const WARD_KEYS = ['wardname', 'ward_name', 'ward', 'name_3', 'adm3_en', 'adm3_name', 'name'];

    private const LGA_KEYS = ['lganame', 'lga_name', 'lga', 'name_2', 'adm2_en', 'adm2_name'];

    private const STATE_KEYS = ['statename', 'state_name', 'state', 'name_1', 'adm1_en', 'adm1_name'];

    public function exists(): bool
    {
        return Storage::disk('local')->exists(self::PATH);
    }

    /**
     * @return array{viewBox: string, wards: array<int, string>, attribution: string}|null
     */
    public function load(): ?array
    {
        return $this->exists() ? json_decode((string) Storage::disk('local')->get(self::PATH), true) : null;
    }

    /**
     * @return array{matched: int, unmatched: list<string>, missing: int}
     */
    public function import(string $path, string $attribution): array
    {
        $geo = json_decode((string) @file_get_contents($path), true);
        if (! is_array($geo) || ! isset($geo['features']) || ! is_array($geo['features'])) {
            throw new RuntimeException('That file isn’t a GeoJSON FeatureCollection.');
        }

        $key = fn (?string $name) => Str::of((string) $name)->lower()->replace(['ward', '/'], ' ')->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
        $lgaIds = Lga::query()->get()->mapWithKeys(fn (Lga $lga) => [$key($lga->name) => $lga->id]);
        $wards = Ward::query()->get()->keyBy(fn (Ward $ward) => $ward->lga_id.'|'.$key($ward->name));

        $shapes = [];
        $unmatched = [];

        foreach ($geo['features'] as $feature) {
            $props = array_change_key_case((array) ($feature['properties'] ?? []), CASE_LOWER);
            $state = $this->first($props, self::STATE_KEYS);
            if ($state !== null && ! str_contains(strtolower($state), 'ebonyi')) {
                continue;
            }

            $wardName = $this->first($props, self::WARD_KEYS);
            $lgaName = $this->first($props, self::LGA_KEYS);
            $lgaId = $lgaIds[$key($lgaName)] ?? null;
            $ward = $lgaId ? ($wards[$lgaId.'|'.$key($wardName)] ?? null) : null;

            if (! $ward) {
                count($unmatched) < 50 && $unmatched[] = trim(($wardName ?? '?').', '.($lgaName ?? '?'));

                continue;
            }

            $shapes[$ward->id] = array_merge($shapes[$ward->id] ?? [], $this->polygons($feature['geometry'] ?? []));
        }

        if ($shapes === []) {
            throw new RuntimeException('No ward in the file matched the register. Check it has ward and LGA names for Ebonyi.');
        }

        [$paths, $viewBox] = $this->project($shapes);
        Storage::disk('local')->put(self::PATH, json_encode(['viewBox' => $viewBox, 'wards' => $paths, 'attribution' => $attribution]));

        return ['matched' => count($paths), 'unmatched' => $unmatched, 'missing' => Ward::query()->count() - count($paths)];
    }

    public function forget(): void
    {
        Storage::disk('local')->delete(self::PATH);
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  list<string>  $keys
     */
    private function first(array $props, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (filled($props[$key] ?? null)) {
                return (string) $props[$key];
            }
        }

        return null;
    }

    /**
     * Outer rings of a Polygon or MultiPolygon, as lists of [lon, lat].
     *
     * @param  array<string, mixed>  $geometry
     * @return list<list<array{0: float, 1: float}>>
     */
    private function polygons(array $geometry): array
    {
        return match ($geometry['type'] ?? null) {
            'Polygon' => [$geometry['coordinates'][0] ?? []],
            'MultiPolygon' => array_map(fn ($polygon) => $polygon[0] ?? [], $geometry['coordinates'] ?? []),
            default => [],
        };
    }

    /**
     * Equirectangular projection scaled to 1000px wide, simplified (points
     * under a pixel apart are dropped), as SVG path data per ward.
     *
     * @param  array<int, list<list<array{0: float, 1: float}>>>  $shapes
     * @return array{0: array<int, string>, 1: string}
     */
    private function project(array $shapes): array
    {
        $lons = [];
        $lats = [];
        foreach ($shapes as $rings) {
            foreach ($rings as $ring) {
                foreach ($ring as [$lon, $lat]) {
                    $lons[] = $lon;
                    $lats[] = $lat;
                }
            }
        }

        [$minLon, $maxLon, $minLat, $maxLat] = [min($lons), max($lons), min($lats), max($lats)];
        $cos = cos(deg2rad(($minLat + $maxLat) / 2));
        $scale = self::WIDTH / max(1e-9, ($maxLon - $minLon) * $cos);
        $height = (int) ceil(($maxLat - $minLat) * $scale);

        $paths = [];
        foreach ($shapes as $wardId => $rings) {
            $d = '';
            foreach ($rings as $ring) {
                $points = [];
                foreach ($ring as [$lon, $lat]) {
                    $point = [round(($lon - $minLon) * $cos * $scale, 1), round(($maxLat - $lat) * $scale, 1)];
                    $last = end($points);
                    if ($last === false || abs($point[0] - $last[0]) + abs($point[1] - $last[1]) >= 1.2) {
                        $points[] = $point;
                    }
                }
                if (count($points) >= 3) {
                    $d .= 'M'.implode('L', array_map(fn ($p) => $p[0].' '.$p[1], $points)).'Z';
                }
            }
            if ($d !== '') {
                $paths[$wardId] = $d;
            }
        }

        return [$paths, '0 0 '.self::WIDTH.' '.$height];
    }
}
