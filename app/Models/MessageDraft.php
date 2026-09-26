<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An AI-drafted message for a segment: three variants, then one approved
 * (possibly edited) by a person, who is recorded. Nothing is sent from
 * here; an approved SMS can start a broadcast.
 */
class MessageDraft extends Model
{
    public const STATUSES = [
        'queued' => ['label' => 'Drafting…', 'tone' => 'info'],
        'ready' => ['label' => 'To review', 'tone' => 'warn'],
        'failed' => ['label' => 'Failed', 'tone' => 'bad'],
        'approved' => ['label' => 'Approved', 'tone' => 'good'],
        'rejected' => ['label' => 'Rejected', 'tone' => null],
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'variants' => 'array', 'approved_at' => 'datetime', 'audience_size' => 'integer'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function aiCall(): BelongsTo
    {
        return $this->belongsTo(AiCall::class);
    }

    public function channelLabel(): string
    {
        return config("messaging.channels.{$this->channel}.label", $this->channel);
    }

    public function languageLabel(): string
    {
        return config("messaging.languages.{$this->language}", $this->language);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status]['label'] ?? $this->status;
    }

    public function statusTone(): ?string
    {
        return self::STATUSES[$this->status]['tone'] ?? null;
    }

    public function limit(): int
    {
        return (int) config("messaging.channels.{$this->channel}.limit", 1000);
    }
}
