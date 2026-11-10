<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ward extends Model
{
    protected $fillable = ['lga_id', 'name', 'slug', 'code', 'register_name', 'registered_voters', 'polling_units_count'];

    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    public function pollingUnits(): HasMany
    {
        return $this->hasMany(PollingUnit::class)->orderBy('code');
    }

    /** Has a name of its own, beyond the register's numbered label. */
    public function hasOwnName(): bool
    {
        return filled($this->register_name) && $this->register_name !== $this->name;
    }

    /**
     * For pickers: "Azuiyiokwu (Ward 01)" once the ward has a name, so the
     * familiar number still shows; "Abakaliki Ward 01" until then.
     */
    public function label(): string
    {
        if (! $this->hasOwnName()) {
            return $this->name;
        }

        $number = preg_match('/\bWard\s+\d+\s*$/i', $this->register_name, $match) ? $match[0] : $this->register_name;

        return "{$this->name} ({$number})";
    }

    /** "Abakaliki Ward 01, Abakaliki" */
    public function fullName(): string
    {
        return $this->name.', '.$this->lga?->name;
    }

    /**
     * The wards a user may see.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->limitToArea($query, 'wards.id');
    }
}
