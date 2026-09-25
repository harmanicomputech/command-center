<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ward extends Model
{
    protected $fillable = ['lga_id', 'name', 'slug', 'registered_voters', 'polling_units_count'];

    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    public function pollingUnits(): HasMany
    {
        return $this->hasMany(PollingUnit::class)->orderBy('code');
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
