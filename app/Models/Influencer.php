<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A community influence note on a ward: an institution or person with a
 * contact and the campaign's relationship with them.
 */
class Influencer extends Model
{
    use BelongsToWard;

    protected $guarded = ['id'];

    protected $hidden = ['contact_phone'];

    protected function casts(): array
    {
        return ['contact_phone' => 'encrypted'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function kindLabel(): string
    {
        return config("structure.influencer_kinds.{$this->kind}", $this->kind);
    }

    public function relationshipLabel(): string
    {
        return config("structure.relationships.{$this->relationship}.label", $this->relationship);
    }

    public function relationshipTone(): ?string
    {
        return config("structure.relationships.{$this->relationship}.tone");
    }
}
