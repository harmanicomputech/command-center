<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyQuestion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['options' => 'array', 'required' => 'boolean', 'position' => 'integer'];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    /**
     * The choices, as [value => label]. Issue and intention questions use
     * the fixed lists; ratings are 1–5.
     *
     * @return array<string, string>
     */
    public function choices(): array
    {
        return match ($this->type) {
            'issue' => config('canvass.issues'),
            'intention' => collect(config('surveys.intention_options'))->map(fn ($option) => $option['label'])->all(),
            'rating' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5'],
            'single', 'multiple' => collect($this->options ?? [])->mapWithKeys(fn ($label, $i) => [(string) ($i + 1) => $label])->all(),
            default => [],
        };
    }
}
