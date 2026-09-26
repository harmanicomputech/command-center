<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * API keys for outside services. The host has no terminal, so besides
 * .env an admin can paste a key on the System page; it is stored encrypted
 * with the app key and never shown again (only its last four characters).
 * A value in .env always wins.
 */
class Secrets
{
    /** [name => [label, config key]] */
    public const KEYS = [
        'anthropic_api_key' => ['Claude (Anthropic) API key', 'services.anthropic.api_key'],
        'africastalking_username' => ['Africa’s Talking username', 'services.africastalking.username'],
        'africastalking_api_key' => ['Africa’s Talking API key', 'services.africastalking.api_key'],
        'africastalking_sender_id' => ['SMS sender ID', 'services.africastalking.sender_id'],
    ];

    public static function get(string $name): ?string
    {
        $fromEnv = config(self::KEYS[$name][1] ?? '');
        if (filled($fromEnv)) {
            return (string) $fromEnv;
        }

        $stored = Settings::get("secret.{$name}");
        if (blank($stored)) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return null;
        }
    }

    public static function set(string $name, ?string $value): void
    {
        Settings::set("secret.{$name}", filled($value) ? Crypt::encryptString(trim($value)) : null);
    }

    public static function fromEnv(string $name): bool
    {
        return filled(config(self::KEYS[$name][1] ?? ''));
    }

    /** "••••abcd", or null when not set. */
    public static function hint(string $name): ?string
    {
        $value = self::get($name);

        return $value === null ? null : '••••'.substr($value, -4);
    }
}
