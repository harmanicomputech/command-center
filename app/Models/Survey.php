<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Survey extends Model
{
    public const DRAFT = 'draft';

    public const LIVE = 'live';

    public const CLOSED = 'closed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['lga_ids' => 'array', 'ward_ids' => 'array', 'channels' => 'array', 'quota_per_ward' => 'integer'];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('position');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasChannel(string $channel): bool
    {
        return in_array($channel, $this->channels ?? [], true);
    }

    /** Whether a ward is in the survey's target (no target = everywhere). */
    public function targets(Ward $ward): bool
    {
        if (! empty($this->ward_ids)) {
            return in_array($ward->id, array_map('intval', $this->ward_ids), true);
        }

        return empty($this->lga_ids) || in_array($ward->lga_id, array_map('intval', $this->lga_ids), true);
    }

    /**
     * Live field surveys that target a ward.
     *
     * @return Collection<int, Survey>
     */
    public static function liveInWard(?Ward $ward): Collection
    {
        if ($ward === null) {
            return new Collection;
        }

        return self::query()->where('status', self::LIVE)->with('questions')->latest()->get()
            ->filter(fn (Survey $survey) => $survey->hasChannel('field') && $survey->targets($ward))->values();
    }

    public function targetLabel(): string
    {
        if (! empty($this->ward_ids)) {
            return count($this->ward_ids).' '.(count($this->ward_ids) === 1 ? 'ward' : 'wards');
        }

        return empty($this->lga_ids) ? 'All of Ebonyi' : Lga::query()->whereIn('id', $this->lga_ids)->orderBy('name')->pluck('name')->implode(', ');
    }
}
