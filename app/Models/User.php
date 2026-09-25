<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'lga_id', 'ward_id', 'invited_by'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(self::class, 'invited_by');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /** At least this rank (a strategist is "at least a coordinator"). */
    public function atLeast(UserRole $role): bool
    {
        return $this->role->rank() >= $role->rank();
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    public function firstName(): string
    {
        return Str::before(trim($this->name), ' ') ?: $this->name;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];

        return Str::upper(Str::substr($parts[0] ?? '?', 0, 1).(count($parts) > 1 ? Str::substr(end($parts), 0, 1) : ''));
    }

    /**
     * Where the user works: "Izzi" or "Abakaliki Ward 01, Abakaliki".
     */
    public function areaLabel(): string
    {
        return match (true) {
            $this->role->isStatewide() => 'All of Ebonyi',
            $this->ward_id !== null => $this->ward?->fullName() ?? '—',
            $this->lga_id !== null => $this->lga?->name ?? '—',
            default => 'No area set',
        };
    }

    /**
     * Limit a query to the wards this user may see. $column holds a ward id.
     * Statewide roles see everything; an LGA leader their LGA's wards; a
     * coordinator or agent their own ward; anyone without an area nothing.
     */
    public function limitToArea(Builder $query, string $column): Builder
    {
        return match (true) {
            $this->role->isStatewide() => $query,
            $this->role === UserRole::LgaLeader && $this->lga_id !== null => $query->whereIn($column, Ward::query()->select('id')->where('lga_id', $this->lga_id)),
            $this->ward_id !== null => $query->where($column, $this->ward_id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function canSeeLga(Lga $lga): bool
    {
        return match (true) {
            $this->role->isStatewide() => true,
            $this->role === UserRole::LgaLeader => $this->lga_id === $lga->id,
            default => $this->ward?->lga_id === $lga->id,
        };
    }

    public function canSeeWard(Ward $ward): bool
    {
        return match (true) {
            $this->role->isStatewide() => true,
            $this->role === UserRole::LgaLeader => $this->lga_id === $ward->lga_id,
            default => $this->ward_id === $ward->id,
        };
    }
}
