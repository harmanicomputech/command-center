<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Ai\AiException;
use App\Services\Ai\NarrativeClusterer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs the AI grouping suggestions in the background (it can take longer
 * than a page load may run); the narratives page polls for the result.
 */
class SuggestNarrativeGroups implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 150;

    public function __construct(public ?int $userId = null)
    {
        $this->onQueue('high');
    }

    public function handle(NarrativeClusterer $clusterer): void
    {
        @set_time_limit(240);

        try {
            $clusterer->suggest($this->userId ? User::query()->find($this->userId) : null);
        } catch (AiException $e) {
            $clusterer->store([], 'failed', $e->getMessage());
        }
    }

    public function failed(): void
    {
        app(NarrativeClusterer::class)->store([], 'failed', 'Grouping stopped unexpectedly. Try again.');
    }
}
