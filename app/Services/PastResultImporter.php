<?php

namespace App\Services;

use App\Models\Lga;
use App\Models\PastResult;
use App\Models\Ward;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Imports past governorship results from CSV: lga,ward,party,votes (ward
 * empty for LGA totals), for one year at a time. Importing a year again
 * replaces that year. Names are matched to the register, ignoring case and
 * spacing; unmatched rows are reported, never guessed.
 */
class PastResultImporter
{
    private const MAX_ERRORS = 50;

    /**
     * @return array{rows: int, errors: list<string>}
     */
    public function import(string $path, int $year): array
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot read {$path}.");
        }

        $header = array_map(fn ($column) => strtolower(trim((string) $column, " \t\n\r\0\x0B\u{FEFF}")), fgetcsv($handle, escape: '\\') ?: []);
        foreach (['lga', 'party', 'votes'] as $required) {
            if (! in_array($required, $header, true)) {
                fclose($handle);
                throw new RuntimeException("The file has no \"{$required}\" column. Expected: lga,ward,party,votes");
            }
        }

        $key = fn (string $name) => Str::of($name)->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
        $lgas = Lga::query()->get()->keyBy(fn (Lga $lga) => $key($lga->name));
        $wards = Ward::query()->get()->keyBy(fn (Ward $ward) => $ward->lga_id.'|'.$key($ward->name));

        $rows = [];
        $errors = [];
        $line = 1;

        while (($values = fgetcsv($handle, escape: '\\')) !== false) {
            $line++;
            if (array_filter($values, fn ($value) => trim((string) $value) !== '') === []) {
                continue;
            }

            $data = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), ''));
            $lga = $lgas[$key((string) $data['lga'])] ?? null;
            $wardName = trim((string) ($data['ward'] ?? ''));
            $ward = $lga && $wardName !== '' ? ($wards[$lga->id.'|'.$key($wardName)] ?? null) : null;
            $party = strtoupper(trim((string) $data['party']));
            $votes = trim((string) $data['votes']);

            $error = match (true) {
                $lga === null => 'unknown LGA "'.trim((string) $data['lga']).'"',
                $wardName !== '' && $ward === null => "unknown ward \"{$wardName}\" in {$lga->name}",
                $party === '' => 'missing party',
                ! ctype_digit($votes) => "invalid votes \"{$votes}\"",
                default => null,
            };

            if ($error !== null) {
                count($errors) < self::MAX_ERRORS && $errors[] = "Line {$line}: {$error}";

                continue;
            }

            $id = $lga->id.'|'.($ward?->id ?? '').'|'.$party;
            $rows[$id] = ['year' => $year, 'lga_id' => $lga->id, 'ward_id' => $ward?->id, 'party' => $party, 'votes' => (int) $votes + ($rows[$id]['votes'] ?? 0)];
        }
        fclose($handle);

        if ($rows === []) {
            return ['rows' => 0, 'errors' => $errors ?: ['The file has no rows.']];
        }

        DB::transaction(function () use ($rows, $year) {
            PastResult::query()->where('year', $year)->delete();
            $now = now();
            foreach (array_chunk(array_values($rows), 500) as $chunk) {
                PastResult::query()->insert(array_map(fn ($row) => [...$row, 'created_at' => $now, 'updated_at' => $now], $chunk));
            }
        });

        return ['rows' => count($rows), 'errors' => $errors];
    }
}
