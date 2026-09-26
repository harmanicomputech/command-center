<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsFeed extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'fetched_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(NewsItem::class);
    }
}
