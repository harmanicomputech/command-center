<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A policy brief in the knowledge base: what the candidate proposes on a
 * topic. Active briefs go into the cached system prompt for AI drafting.
 */
class PolicyDocument extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function topicLabel(): string
    {
        return config("messaging.policy_topics.{$this->topic}", $this->topic);
    }
}
