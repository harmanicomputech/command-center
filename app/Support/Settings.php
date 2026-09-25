<?php

namespace App\Support;

use App\Models\Setting;
use Throwable;

/**
 * Settings changed in the console (the host has no terminal to edit .env).
 * Values the owner can edit on the Settings page are declared, with their
 * defaults, in SettingsRegistry; internal state (runner slots, heartbeats)
 * uses plain keys.
 */
class Settings
{
    /** @var array<string, string|null>|null */
    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        if (self::$cache === null) {
            try {
                self::$cache = Setting::query()->pluck('value', 'key')->all();
            } catch (Throwable) {
                return $default ?? SettingsRegistry::default($key);
            }
        }

        return self::$cache[$key] ?? $default ?? SettingsRegistry::default($key);
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    public static function float(string $key): float
    {
        return (float) self::get($key);
    }

    public static function set(string $key, ?string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        self::$cache = null;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
