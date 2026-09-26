<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One request to Claude: purpose, model, tokens and cost (US dollars).
 */
class AiCall extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cost_usd' => 'float', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Spend since the start of this month (UTC). */
    public static function monthSpend(): float
    {
        return (float) self::query()->where('created_at', '>=', now()->startOfMonth())->sum('cost_usd');
    }
}
