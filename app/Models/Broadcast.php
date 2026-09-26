<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An SMS broadcast to a segment of consenting voters or to the team.
 * Adapted from Election Shield: the recipient list is frozen when sending
 * starts and sent in batches from the queue.
 */
class Broadcast extends Model
{
    public const DRAFT = 'draft';

    public const SENDING = 'sending';

    public const SENT = 'sent';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [
        'draft' => ['label' => 'Draft', 'tone' => null],
        'sending' => ['label' => 'Sending', 'tone' => 'info'],
        'sent' => ['label' => 'Sent', 'tone' => 'good'],
        'cancelled' => ['label' => 'Cancelled', 'tone' => null],
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['audience' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(BroadcastMessage::class);
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(MessageDraft::class, 'message_draft_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function editable(): bool
    {
        return $this->status === self::DRAFT;
    }

    /**
     * @return array<string, int> by status
     */
    public function counts(): array
    {
        return $this->messages()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($n) => (int) $n)->all();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status]['label'] ?? $this->status;
    }

    public function statusTone(): ?string
    {
        return self::STATUSES[$this->status]['tone'] ?? null;
    }
}
