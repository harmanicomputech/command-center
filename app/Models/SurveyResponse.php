<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyResponse extends Model
{
    use BelongsToWard;

    protected $guarded = ['id'];

    protected $hidden = ['phone_hash'];

    protected function casts(): array
    {
        return ['answers' => 'array', 'answered_at' => 'datetime'];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
