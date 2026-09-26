<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A story going round (a rumour, an opposition line, a complaint), made of
 * the reports that were grouped into it.
 */
class Narrative extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['alerted_at' => 'datetime'];
    }

    public function reports(): HasMany
    {
        return $this->hasMany(NarrativeReport::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Narratives with at least one report the user may see (statewide roles see all). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->role->isStatewide() ? $query : $query->whereHas('reports', fn (Builder $reports) => $reports->visibleTo($user));
    }

    public function topicLabel(): string
    {
        return config("messaging.narrative_topics.{$this->topic}", $this->topic);
    }

    public function toneLabel(): string
    {
        return config("messaging.narrative_tones.{$this->tone}.label", $this->tone);
    }

    public function toneTone(): ?string
    {
        return config("messaging.narrative_tones.{$this->tone}.tone");
    }

    public function statusLabel(): string
    {
        return config("messaging.narrative_statuses.{$this->status}.label", $this->status);
    }

    public function statusTone(): ?string
    {
        return config("messaging.narrative_statuses.{$this->status}.tone");
    }
}
