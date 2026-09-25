<?php

namespace App\Services;

use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\Ward;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Imports the PU register from CSV: code,name,ward,lga,registered_voters
 * (the same file as Election Shield). LGAs and wards are created from it;
 * polling units are upserted by code, so importing again or a newer file
 * updates rather than duplicates. Ward and LGA totals are recounted after.
 */
class PollingUnitImporter
{
    public const BUNDLED = 'data/ebonyi_polling_units.csv';

    private const MAX_ERRORS = 50;

    public static function bundledPath(): string
    {
        return database_path(self::BUNDLED);
    }

    /**
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function import(string $path): array
    {
        $handle = @fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Cannot read {$path}.");
        }

        $header = array_map(fn ($column) => strtolower(trim((string) $column, " \t\n\r\0\x0B\u{FEFF}")), fgetcsv($handle, escape: '\\') ?: []);

        foreach (['code', 'name', 'ward', 'lga'] as $required) {
            if (! in_array($required, $header, true)) {
                fclose($handle);
                throw new RuntimeException("The file has no \"{$required}\" column. Expected: code,name,ward,lga,registered_voters");
            }
        }

        $known = collect(config('campaign.lgas'))->keyBy(fn (string $name) => Str::lower($name));
        $rows = [];
        $errors = [];
        $line = 1;

        while (($values = fgetcsv($handle, escape: '\\')) !== false) {
            $line++;

            if (array_filter($values, fn ($value) => trim((string) $value) !== '') === []) {
                continue;
            }

            $data = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), ''));
            $code = PollingUnit::normalizeCode((string) $data['code']);
            $registered = trim((string) ($data['registered_voters'] ?? ''));
            $lga = $known->get(Str::lower(trim((string) $data['lga'])));

            $error = match (true) {
                strlen($code) !== 11 => "invalid code \"{$data['code']}\"",
                trim((string) $data['ward']) === '' => 'missing ward',
                $lga === null => 'unknown LGA "'.trim((string) $data['lga']).'"',
                $registered !== '' && ! ctype_digit($registered) => "invalid registered_voters \"{$registered}\"",
                default => null,
            };

            if ($error !== null) {
                count($errors) < self::MAX_ERRORS && $errors[] = "Line {$line}: {$error}";

                continue;
            }

            $rows[$code] = [
                'code' => $code,
                'name' => trim((string) $data['name']) ?: null,
                'ward' => preg_replace('/\s+/', ' ', trim((string) $data['ward'])),
                'lga' => $lga,
                'registered_voters' => $registered === '' ? null : (int) $registered,
            ];
        }

        fclose($handle);

        $existing = PollingUnit::query()->whereIn('code', array_keys($rows))->count();

        DB::transaction(function () use ($rows) {
            $now = now();
            $lgaIds = [];
            $wardIds = [];

            foreach (array_unique(array_column($rows, 'lga')) as $name) {
                $lgaIds[$name] = Lga::query()->firstOrCreate(['name' => $name], ['slug' => Str::slug($name)])->id;
            }

            foreach ($rows as $row) {
                $key = $row['lga'].'|'.$row['ward'];
                $wardIds[$key] ??= Ward::query()->firstOrCreate(
                    ['lga_id' => $lgaIds[$row['lga']], 'name' => $row['ward']],
                    ['slug' => Str::slug($row['ward'])],
                )->id;
            }

            foreach (array_chunk(array_values($rows), 500) as $chunk) {
                PollingUnit::query()->upsert(
                    array_map(fn ($row) => [
                        'code' => $row['code'],
                        'name' => $row['name'],
                        'ward_id' => $wardIds[$row['lga'].'|'.$row['ward']],
                        'registered_voters' => $row['registered_voters'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], $chunk),
                    ['code'],
                    ['name', 'ward_id', 'registered_voters', 'updated_at'],
                );
            }

            $this->recount();
        });

        return ['created' => count($rows) - $existing, 'updated' => $existing, 'errors' => $errors];
    }

    /**
     * Ward and LGA totals, kept on the rows so maps and tables stay cheap.
     */
    public function recount(): void
    {
        $wards = PollingUnit::query()
            ->selectRaw('ward_id, count(*) as units, coalesce(sum(registered_voters), 0) as voters')
            ->groupBy('ward_id')->get()->keyBy('ward_id');

        Ward::query()->each(function (Ward $ward) use ($wards) {
            $ward->update([
                'polling_units_count' => (int) ($wards[$ward->id]->units ?? 0),
                'registered_voters' => (int) ($wards[$ward->id]->voters ?? 0),
            ]);
        });

        Lga::query()->each(function (Lga $lga) {
            $wards = Ward::query()->where('lga_id', $lga->id);
            $lga->update([
                'wards_count' => (clone $wards)->count(),
                'polling_units_count' => (int) (clone $wards)->sum('polling_units_count'),
                'registered_voters' => (int) (clone $wards)->sum('registered_voters'),
            ]);
        });
    }
}
