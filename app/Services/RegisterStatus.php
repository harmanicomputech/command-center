<?php

namespace App\Services;

use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\Ward;
use App\Support\Settings;

/**
 * The PU register's size and whether an admin has confirmed it. The
 * bundled file totals about 4.6 million registered voters, roughly three
 * times INEC's 2023 figure for Ebonyi, so the System page warns until an
 * admin confirms it or uploads INEC's register. Every priority-zone
 * calculation rests on these numbers.
 */
class RegisterStatus
{
    /** INEC's 2023 figure for Ebonyi, roughly: the yardstick for the warning. */
    public const INEC_2023_APPROX = 1_600_000;

    /**
     * @return array{units: int, wards: int, lgas: int, registered: int, confirmed: bool, confirmed_by: ?string, confirmed_at: ?string, source: string, ratio: float}
     */
    public function summary(): array
    {
        $registered = (int) PollingUnit::query()->sum('registered_voters');

        return [
            'units' => PollingUnit::query()->count(),
            'wards' => Ward::query()->where('polling_units_count', '>', 0)->count(),
            'lgas' => Lga::query()->where('polling_units_count', '>', 0)->count(),
            'registered' => $registered,
            'confirmed' => filled(Settings::get('register.confirmed_at')),
            'confirmed_by' => Settings::get('register.confirmed_by'),
            'confirmed_at' => Settings::get('register.confirmed_at'),
            'source' => Settings::get('register.source') ?? 'the bundled register',
            'ratio' => round($registered / self::INEC_2023_APPROX, 1),
        ];
    }

    public function needsWarning(): bool
    {
        return ! filled(Settings::get('register.confirmed_at'));
    }
}
