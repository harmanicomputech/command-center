<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reward extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['week_of' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function giver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'given_by');
    }

    public function kindLabel(): string
    {
        return config("field.reward_kinds.{$this->kind}", $this->kind);
    }
}
