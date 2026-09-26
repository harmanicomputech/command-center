<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone who offered to help, from the /join page or the website.
 */
class Volunteer extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['phone', 'phone_hash'];

    protected function casts(): array
    {
        return ['phone' => 'encrypted', 'help' => 'array', 'consent_at' => 'datetime', 'handled_at' => 'datetime'];
    }

    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * Statewide roles see everyone; LGA leaders their LGA; coordinators
     * their ward, plus sign-ups in their LGA that gave no ward.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match (true) {
            $user->role->isStatewide() => $query,
            $user->role === UserRole::LgaLeader => $query->where('volunteers.lga_id', $user->lga_id),
            $user->ward_id !== null => $query->where(fn (Builder $query) => $query->where('volunteers.ward_id', $user->ward_id)
                ->orWhere(fn (Builder $query) => $query->whereNull('volunteers.ward_id')->where('volunteers.lga_id', $user->ward?->lga_id))),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function statusLabel(): string
    {
        return config("volunteers.statuses.{$this->status}.label", $this->status);
    }

    public function statusTone(): ?string
    {
        return config("volunteers.statuses.{$this->status}.tone");
    }

    /** @return list<string> */
    public function helpLabels(): array
    {
        return array_values(array_filter(array_map(fn ($key) => config("volunteers.help.{$key}"), $this->help ?? [])));
    }
}
