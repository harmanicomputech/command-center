<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A headline from a tracked feed, with the alert keywords it matched.
 */
class NewsItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['keywords' => 'array', 'published_at' => 'datetime', 'starred' => 'boolean'];
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(NewsFeed::class, 'news_feed_id');
    }
}
