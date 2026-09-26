<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A meeting or event in an LGA (and usually a ward).
 */
class Event extends Model
{
    public const PLANNED = 'planned';

    public const HELD = 'held';

    public const CANCELLED = 'cancelled';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'expected' => 'integer', 'attendance' => 'integer'];
    }

    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('created_at');
    }

    /**
     * Events a user may see: statewide roles all; an LGA leader their LGA;
     * coordinators and agents their ward's events and LGA-wide ones.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match (true) {
            $user->role->isStatewide() => $query,
            $user->role === UserRole::LgaLeader => $query->where('events.lga_id', $user->lga_id),
            $user->ward_id !== null => $query->where('events.lga_id', $user->ward?->lga_id)
                ->where(fn (Builder $inner) => $inner->whereNull('events.ward_id')->orWhere('events.ward_id', $user->ward_id)),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function typeLabel(): string
    {
        return config("structure.event_types.{$this->type}", $this->type);
    }

    public function place(): string
    {
        return $this->ward ? $this->ward->name.', '.$this->lga?->name : ($this->lga?->name.' (LGA-wide)');
    }
}
