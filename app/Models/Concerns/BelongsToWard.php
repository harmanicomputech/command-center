<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For records that belong to a ward (voters, tasks, issues, responses):
 * the relation and the server-side area scope.
 */
trait BelongsToWard
{
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * Records in the wards the user may see.
     */
    public function scopeInAreaOf(Builder $query, User $user): Builder
    {
        return $user->limitToArea($query, $query->getModel()->qualifyColumn('ward_id'));
    }
}
