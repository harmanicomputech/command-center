<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Support\Str;

/**
 * Places agents imported with only their ward's name as a list wrote it
 * ("ISHIEKE WARD 2", "Ndiagu Ward II") into the register's ward of
 * that name, in their LGA, once the ward has it: when an admin names wards
 * or INEC's register is uploaded. Names are compared loosely (case,
 * punctuation, word order, the word "ward", roman numerals).
 */
class WardMatcher
{
    private const NUMBERS = ['i' => '1', 'ii' => '2', 'iii' => '3', 'iv' => '4', 'v' => '5', 'one' => '1', 'two' => '2', 'three' => '3', 'four' => '4', 'five' => '5'];

    public static function key(?string $name): string
    {
        $words = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii((string) $name)), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_filter($words, fn ($word) => ! in_array($word, ['ward', 'pdp', 'list', 'lga'], true));

        $words = array_map(fn ($word) => ctype_digit($word) ? (string) (int) $word : (self::NUMBERS[$word] ?? $word), $words);
        sort($words); // "Ward 013 – Ndiegu" is "Ndiegu Ward 13"

        return implode(' ', $words);
    }

    /**
     * The ward in this LGA whose name (or register label) matches.
     */
    public function find(int $lgaId, ?string $label): ?Ward
    {
        $key = self::key($label);
        if ($key === '') {
            return null;
        }

        $matches = Ward::query()->where('lga_id', $lgaId)->get()
            ->filter(fn (Ward $ward) => self::key($ward->name) === $key || self::key($ward->register_name) === $key);

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * Place every waiting agent whose list ward now matches a ward. Returns how many.
     */
    public function placeWaitingAgents(?int $lgaId = null): int
    {
        $placed = 0;

        User::query()->where('role', UserRole::Agent)->whereNull('ward_id')->whereNotNull('ward_label')->whereNotNull('lga_id')
            ->when($lgaId, fn ($query) => $query->where('lga_id', $lgaId))
            ->get()->groupBy('lga_id')
            ->each(function ($agents, $lga) use (&$placed) {
                $wards = Ward::query()->where('lga_id', $lga)->get();
                $index = [];
                foreach ($wards as $ward) {
                    foreach (array_unique([self::key($ward->name), self::key($ward->register_name)]) as $key) {
                        $index[$key][] = $ward->id;
                    }
                }
                foreach ($agents as $agent) {
                    $ids = array_unique($index[self::key($agent->ward_label)] ?? []);
                    if (count($ids) === 1) {
                        $agent->forceFill(['ward_id' => $ids[0]])->saveQuietly();
                        $placed++;
                    }
                }
            });

        return $placed;
    }
}
