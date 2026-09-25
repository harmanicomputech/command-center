<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWard;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A voter registered by the field. The phone is encrypted at rest; search
 * and de-duplication use phone_hash (see App\Support\Phone::hash).
 */
class Voter extends Model
{
    use BelongsToWard;

    public const UNVERIFIED = 'unverified';

    public const VERIFIED = 'verified';

    public const INVALID = 'invalid';

    protected $guarded = ['id'];

    protected $hidden = ['phone', 'phone_hash'];

    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'consent_at' => 'datetime',
            'captured_at' => 'datetime',
            'verified_at' => 'datetime',
            'opted_out_at' => 'datetime',
            'erased_at' => 'datetime',
            'possible_duplicate' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    public function pollingUnit(): BelongsTo
    {
        return $this->belongsTo(PollingUnit::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function original(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of');
    }

    /** Counts towards points and totals: not invalid, not an unresolved duplicate. */
    public function scopeCounted(Builder $query): Builder
    {
        return $query->where('voters.status', '!=', self::INVALID)->where('voters.possible_duplicate', false);
    }

    /** Records a user may see: agents their own, everyone else their area. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->role->usesFieldApp()
            ? $query->where('voters.captured_by', $user->id)
            : $this->scopeInAreaOf($query, $user);
    }

    public function supportLabel(): string
    {
        return config("canvass.support_levels.{$this->support_level}.label", $this->support_level);
    }

    public function supportTone(): string
    {
        return config("canvass.support_levels.{$this->support_level}.tone", 'info');
    }

    /** The number as staff see it (coordinators and above). */
    public function phoneForStaff(): string
    {
        return $this->phone ? Phone::local($this->phone) : '—';
    }

    /** The number as its agent sees it once synced: 0803 *** **21. */
    public function phoneMasked(): string
    {
        return $this->phone ? Phone::mask($this->phone) : '—';
    }
}
