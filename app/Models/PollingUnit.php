<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PollingUnit extends Model
{
    protected $fillable = ['code', 'name', 'ward_id', 'registered_voters'];

    /**
     * INEC codes like EB/212/02633/007 are stored as digits: 21202633007.
     */
    public static function normalizeCode(string $code): string
    {
        return preg_replace('/\D/', '', $code) ?? '';
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * EB/212/02633/007 for a stored code of 21202633007.
     */
    public function inecCode(): string
    {
        return strlen($this->code) === 11
            ? 'EB/'.substr($this->code, 0, 3).'/'.substr($this->code, 3, 5).'/'.substr($this->code, 8)
            : $this->code;
    }
}
