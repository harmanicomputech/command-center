<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Times are stored in UTC and shown in the campaign's timezone (Lagos).
 */
class Time
{
    public static function parse(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    public static function zone(): string
    {
        return (string) config('campaign.timezone');
    }

    /**
     * A stored time in the campaign timezone, e.g. "3:42 PM".
     */
    public static function local(?Carbon $time, string $format = 'g:i A'): string
    {
        return $time ? $time->copy()->setTimezone(self::zone())->format($format) : '—';
    }

    /** Now, in Lagos. */
    public static function now(): Carbon
    {
        return now()->setTimezone(self::zone());
    }

    /** "Good morning" / "Good afternoon" / "Good evening", Lagos time. */
    public static function greeting(): string
    {
        $hour = (int) self::now()->format('G');

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    /** Whole days until the election (0 on the day, negative after). */
    public static function daysToElection(): int
    {
        $election = Carbon::parse((string) config('campaign.election_date'), self::zone())->startOfDay();

        return (int) self::now()->startOfDay()->diffInDays($election, false);
    }
}
