<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A post on the campaign's own page, with its engagement (typed in or
 * imported from a CSV export of the page's insights).
 */
class PagePost extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['posted_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function engagement(): int
    {
        return (int) $this->reactions + (int) $this->comments + (int) $this->shares;
    }

    public function platformLabel(): string
    {
        return config("messaging.platforms.{$this->platform}", $this->platform);
    }
}
