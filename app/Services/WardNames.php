<?php

namespace App\Services;

use App\Models\Lga;
use App\Models\Ward;
use App\Support\Aggregates;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Wards' real names. The bundled register numbers its wards ("Abakaliki
 * Ward 01"); an admin names them one at a time (the ward page) or all at
 * once from a CSV (System page). The register's label is kept, so pickers
 * show both ("Azuiyiokwu (Ward 01)") and a later register import still
 * finds the ward. Clearing a name goes back to the register's label.
 */
class WardNames
{
    public const COLUMNS = ['lga', 'ward_code', 'register_name', 'name'];

    public function rename(Ward $ward, ?string $name): bool
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $name));
        $name = $name === '' ? ($ward->register_name ?? $ward->name) : $name;

        if ($name === $ward->name) {
            return false;
        }
        if (mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'Keep the name under 120 characters.']);
        }
        if (Ward::query()->where('lga_id', $ward->lga_id)->where('name', $name)->whereKeyNot($ward->id)->exists()) {
            throw ValidationException::withMessages(['name' => "Another ward in {$ward->lga->name} is already called {$name}."]);
        }

        $ward->forceFill(['name' => $name, 'register_name' => $ward->register_name ?? $ward->name])->save();
        Aggregates::flush();

        return true;
    }

    /**
     * Every ward, for the CSV an admin fills in: lga, ward_code, register_name, name.
     *
     * @return list<list<string>>
     */
    public function rows(): array
    {
        return Ward::query()->with('lga')->get()
            ->sortBy([fn (Ward $ward) => $ward->lga->name, fn (Ward $ward) => $ward->register_name ?? $ward->name])
            ->map(fn (Ward $ward) => [$ward->lga->name, (string) $ward->code, (string) ($ward->register_name ?? $ward->name), $ward->hasOwnName() ? $ward->name : ''])
            ->values()->all();
    }

    /**
     * Names from that CSV. A row finds its ward by ward_code, or by LGA and
     * register name (or current name); an empty name leaves the ward as it is.
     *
     * @return array{renamed: int, unchanged: int, errors: list<string>}
     */
    public function import(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = array_map(fn ($value) => Str::snake(trim(str_replace("\u{FEFF}", '', (string) $value))), fgetcsv($handle, escape: '\\') ?: []);
        if (! in_array('name', $header, true) || ! array_intersect(['ward_code', 'register_name'], $header)) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'The file needs a "name" column and a "ward_code" or "register_name" column. Download the ward list to start from.']);
        }

        $lgas = Lga::query()->get()->keyBy(fn (Lga $lga) => Str::lower($lga->name));
        $result = ['renamed' => 0, 'unchanged' => 0, 'errors' => []];
        $line = 1;

        while (($values = fgetcsv($handle, escape: '\\')) !== false) {
            $line++;
            $row = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), ''));
            if (trim((string) $row['name']) === '') {
                $result['unchanged']++;

                continue;
            }

            $lga = $lgas->get(Str::lower(trim((string) ($row['lga'] ?? ''))));
            $code = preg_replace('/\D/', '', (string) ($row['ward_code'] ?? ''));
            $label = trim((string) ($row['register_name'] ?? ''));
            $ward = $code !== '' ? Ward::query()->where('code', $code)->first() : null;
            $ward ??= $lga && $label !== '' ? Ward::query()->where('lga_id', $lga->id)->where(fn ($query) => $query->where('register_name', $label)->orWhere('name', $label))->first() : null;

            if ($ward === null) {
                count($result['errors']) < 10 && $result['errors'][] = "Line {$line}: no ward matches";

                continue;
            }

            try {
                $this->rename($ward, (string) $row['name']) ? $result['renamed']++ : $result['unchanged']++;
            } catch (ValidationException $e) {
                count($result['errors']) < 10 && $result['errors'][] = "Line {$line}: ".collect($e->errors())->flatten()->first();
            }
        }

        fclose($handle);

        return $result;
    }
}
