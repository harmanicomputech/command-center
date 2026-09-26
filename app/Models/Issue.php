<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWard;
use App\Models\Concerns\HasPhotos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A community issue from the field: a bad road, no water, insecurity…
 * The raw material for "top issues per LGA" and solution-driven messages.
 */
class Issue extends Model
{
    use BelongsToWard, HasPhotos;

    /** Statuses that mean the report was accepted (and earns points). */
    public const ACCEPTED = ['noted', 'used', 'addressed'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reported_at' => 'datetime', 'status_at' => 'datetime', 'people_affected' => 'integer'];
    }

    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /** Agents see their own reports; everyone else their area. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->role->usesFieldApp() ? $query->where('issues.reported_by', $user->id) : $this->scopeInAreaOf($query, $user);
    }

    public function categoryLabel(): string
    {
        return config("field.issue_categories.{$this->category}", $this->category);
    }

    public function severityLabel(): string
    {
        return config("field.severities.{$this->severity}.label", $this->severity);
    }

    public function severityTone(): ?string
    {
        return config("field.severities.{$this->severity}.tone");
    }

    public function statusLabel(): string
    {
        return config("field.issue_statuses.{$this->status}.label", $this->status);
    }

    public function statusTone(): ?string
    {
        return config("field.issue_statuses.{$this->status}.tone");
    }
}
