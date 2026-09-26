<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recipient of a broadcast. The phone number stays on the voter or
 * user record (encrypted); it is read only at the moment of sending.
 */
class BroadcastMessage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    public function phone(): ?string
    {
        return match ($this->recipient_type) {
            'voter' => Voter::query()->whereKey($this->recipient_id)->whereNull('erased_at')->whereNull('opted_out_at')->first()?->phone,
            'user' => User::query()->whereKey($this->recipient_id)->first()?->phone,
            default => null,
        };
    }
}
