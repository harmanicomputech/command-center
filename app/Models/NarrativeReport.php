<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWard;
use App\Models\Concerns\HasPhotos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sighting of something people are saying: where it was seen (source),
 * a screenshot or photo, an optional link, the area, a topic and a tone.
 */
class NarrativeReport extends Model
{
    use BelongsToWard, HasPhotos;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['seen_at' => 'datetime'];
    }

    public function narrative(): BelongsTo
    {
        return $this->belongsTo(Narrative::class);
    }

    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * Agents see their own; statewide roles everything; others their area
     * (a report with no area is statewide and seen by statewide roles only).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role->usesFieldApp()) {
            return $query->where('narrative_reports.reported_by', $user->id);
        }

        return $user->role->isStatewide() ? $query : $this->scopeInAreaOf($query, $user);
    }

    public function sourceLabel(): string
    {
        return config("messaging.sources.{$this->source}", $this->source);
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

    public function place(): string
    {
        return $this->ward?->fullName() ?? $this->lga?->name ?? 'Statewide';
    }
}
