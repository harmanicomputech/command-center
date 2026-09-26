<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A task for one agent, or for everyone in a ward (assignee_id null).
 */
class Task extends Model
{
    use BelongsToWard;

    public const OPEN = 'open';

    public const CLOSED = 'closed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'target' => 'integer'];
    }

    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(TaskReport::class)->orderBy('reported_at');
    }

    /** Open tasks for an agent: theirs, or their ward's. */
    public function scopeFor(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $inner) => $inner->where('assignee_id', $user->id)
            ->orWhere(fn (Builder $ward) => $ward->whereNull('assignee_id')->where('ward_id', $user->ward_id)));
    }

    public function typeLabel(): string
    {
        return config("field.task_types.{$this->type}", $this->type);
    }

    public function isOverdue(): bool
    {
        return $this->status === self::OPEN && $this->due_on !== null && $this->due_on->endOfDay()->isPast();
    }

    public function progressFor(User $user): int
    {
        return (int) $this->reports->where('user_id', $user->id)->sum('count');
    }

    public function doneBy(User $user): bool
    {
        return $this->reports->where('user_id', $user->id)->contains('done', true);
    }

    public function targetLabel(): ?string
    {
        return $this->target ? number_format($this->target).' '.($this->target_unit ?? '') : null;
    }
}
