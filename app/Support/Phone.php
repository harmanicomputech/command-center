<?php

namespace App\Support;

/**
 * Nigerian phone numbers in E.164: 08031234567, 2348031234567 and
 * +234 803 123 4567 all become +2348031234567.
 */
class Phone
{
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone) ?? '';

        $digits = match (true) {
            str_starts_with($digits, '234') && strlen($digits) === 13 => $digits,
            str_starts_with($digits, '0') && strlen($digits) === 11 => '234'.substr($digits, 1),
            strlen($digits) === 10 && in_array($digits[0], ['7', '8', '9'], true) => '234'.$digits,
            default => null,
        };

        return $digits === null ? null : '+'.$digits;
    }

    /**
     * The digits to search on: a leading 234 or 0 stripped, so "0802…",
     * "234802…" and "+234 802…" all find the same number.
     */
    public static function searchDigits(string $input): string
    {
        $digits = preg_replace('/\D/', '', $input) ?? '';

        return match (true) {
            str_starts_with($digits, '234') => substr($digits, 3),
            str_starts_with($digits, '0') => substr($digits, 1),
            default => $digits,
        };
    }

    /**
     * SHA-256 of the normalised number, keyed with APP_KEY: stored next to
     * the encrypted number for de-duplication and exact search.
     */
    public static function hash(?string $phone): ?string
    {
        $normalized = self::normalize($phone);

        return $normalized === null ? null : hash_hmac('sha256', $normalized, (string) config('app.key'));
    }

    /** 0803 123 4521 */
    public static function local(?string $phone): string
    {
        $normalized = self::normalize($phone);

        if ($normalized === null) {
            return (string) $phone;
        }

        $local = '0'.substr($normalized, 4);

        return substr($local, 0, 4).' '.substr($local, 4, 3).' '.substr($local, 7);
    }

    /** 0803 *** **21 */
    public static function mask(?string $phone): string
    {
        $normalized = self::normalize($phone);

        if ($normalized === null) {
            return '—';
        }

        $local = '0'.substr($normalized, 4);

        return substr($local, 0, 4).' *** **'.substr($local, -2);
    }
}
