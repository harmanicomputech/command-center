<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A "delete my data" request. The phone number is kept (encrypted) only
 * while the request is open.
 */
class DataRequest extends Model
{
    public const CHANNELS = ['web' => 'Privacy page', 'sms' => 'SMS “DELETE”', 'staff' => 'Recorded by staff'];

    public const STATUSES = [
        'pending' => ['label' => 'To check', 'tone' => 'warn'],
        'done' => ['label' => 'Erased', 'tone' => 'good'],
        'rejected' => ['label' => 'Closed, nothing erased', 'tone' => null],
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['phone' => 'encrypted', 'handled_at' => 'datetime'];
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** Voter records still holding personal data for this number. */
    public function matches(): int
    {
        return $this->phone_hash ? Voter::query()->where('phone_hash', $this->phone_hash)->whereNull('erased_at')->count() : 0;
    }
}
