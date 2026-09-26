<?php

namespace App\Models;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;

/**
 * A number that replied STOP. Kept as a keyed hash only, so it outlives an
 * erased voter record and is still honoured if the number is registered again.
 */
class SmsOptOut extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public static function record(string $phone, string $source): void
    {
        $hash = Phone::hash($phone);
        if ($hash === null) {
            return;
        }

        self::query()->firstOrCreate(['phone_hash' => $hash], ['source' => $source, 'created_at' => now()]);
        Voter::query()->where('phone_hash', $hash)->whereNull('opted_out_at')->update(['opted_out_at' => now()]);
    }

    public static function has(?string $hash): bool
    {
        return $hash !== null && self::query()->where('phone_hash', $hash)->exists();
    }
}
