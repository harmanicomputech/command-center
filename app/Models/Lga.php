<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lga extends Model
{
    protected $fillable = ['name', 'slug', 'registered_voters', 'wards_count', 'polling_units_count'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function wards(): HasMany
    {
        return $this->hasMany(Ward::class)->orderBy('name');
    }

    /**
     * The LGAs a user may see.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match (true) {
            $user->role->isStatewide() => $query,
            $user->lga_id !== null => $query->whereKey($user->lga_id),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
