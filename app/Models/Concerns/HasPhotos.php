<?php

namespace App\Models\Concerns;

use App\Models\Photo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasPhotos
{
    public function photos(): MorphMany
    {
        return $this->morphMany(Photo::class, 'owner')->orderBy('id');
    }
}
