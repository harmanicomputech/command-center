<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Lga;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Field agents from a list (CSV: name, phone, lga, ward). Each signs in with
 * their phone number and a PIN made of its last 4 digits, and can be made to
 * choose their own PIN at their first sign-in. The ward is matched to the
 * register by name (WardMatcher); until it matches, the agent works in their
 * LGA and the list's ward name is kept to place them later.
 *
 * Skipped, never guessed: numbers that aren't Nigerian mobiles, numbers
 * already on the app, numbers that appear twice in the file (two people
 * can't share a sign-in), rows without a name, and unknown LGAs.
 */
class AgentImporter
{
    public const MAX_ROWS = 10000;

    public function __construct(private WardMatcher $matcher) {}

    /**
     * @return array{created: int, placed: int, skipped: list<array{line: int, name: string, reason: string}>}
     */
    public function import(string $path, User $by, bool $mustChangePin = true): array
    {
        $rows = $this->read($path);
        $lgas = Lga::query()->get()->keyBy(fn (Lga $lga) => Str::lower($lga->name));
        $existing = User::query()->whereNotNull('phone')->pluck('phone')->flip();
        $counts = collect($rows)->map(fn ($row) => Phone::normalize($row['phone']))->filter()->countBy();
        $skipped = [];
        $insert = [];
        $now = now();

        foreach ($rows as $row) {
            $phone = Phone::normalize($row['phone']);
            $lga = $lgas->get(Str::lower(trim($row['lga'])));
            $reason = match (true) {
                trim($row['name']) === '' => 'No name',
                trim($row['phone']) === '' => 'No phone number',
                $phone === null => 'Not a Nigerian mobile number: '.$row['phone'],
                $counts[$phone] > 1 => 'The same number is on '.$counts[$phone].' rows: each agent needs their own',
                isset($existing[$phone]) => 'This number already has an account',
                $lga === null => 'Unknown LGA: '.($row['lga'] ?: '(empty)'),
                default => null,
            };

            if ($reason !== null) {
                $skipped[] = ['line' => $row['line'], 'name' => trim($row['name']), 'reason' => $reason];

                continue;
            }

            $label = trim($row['ward']) ?: null;
            $ward = $this->matcher->find($lga->id, $label);
            $insert[] = [
                'name' => Str::of($row['name'])->squish()->title()->toString(),
                'phone' => $phone,
                // A 4-digit PIN has only 10,000 values, so a slow hash adds nothing here and
                // would take minutes for thousands of agents; it is upgraded at first sign-in.
                'password' => Hash::make(substr($phone, -4), ['rounds' => 4]),
                'role' => UserRole::Agent->value,
                'lga_id' => $lga->id,
                'ward_id' => $ward?->id,
                'ward_label' => $label,
                'must_change_pin' => $mustChangePin,
                'invited_by' => $by->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $existing[$phone] = true;
        }

        DB::transaction(fn () => collect($insert)->chunk(500)->each(fn ($chunk) => DB::table('users')->insert($chunk->values()->all())));

        return [
            'created' => count($insert),
            'placed' => collect($insert)->whereNotNull('ward_id')->count(),
            'skipped' => $skipped,
        ];
    }

    /**
     * @return list<array{line: int, name: string, phone: string, lga: string, ward: string}>
     */
    private function read(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = array_map(fn ($value) => Str::snake(trim(str_replace("\u{FEFF}", '', (string) $value))), fgetcsv($handle, escape: '\\') ?: []);
        foreach (['name', 'phone', 'lga'] as $required) {
            if (! in_array($required, $header, true)) {
                fclose($handle);
                throw ValidationException::withMessages(['file' => 'The file needs these columns: name, phone, lga, and optionally ward.']);
            }
        }

        $rows = [];
        $line = 1;
        while (($values = fgetcsv($handle, escape: '\\')) !== false) {
            $line++;
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            $row = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), ''));
            $rows[] = ['line' => $line, 'name' => (string) $row['name'], 'phone' => (string) $row['phone'], 'lga' => (string) $row['lga'], 'ward' => (string) ($row['ward'] ?? '')];
            if (count($rows) > self::MAX_ROWS) {
                fclose($handle);
                throw ValidationException::withMessages(['file' => 'Split the file: at most '.number_format(self::MAX_ROWS).' agents at a time.']);
            }
        }
        fclose($handle);

        return $rows;
    }
}
