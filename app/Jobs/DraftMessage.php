<?php

namespace App\Jobs;

use App\Models\MessageDraft;
use App\Services\Ai\MessageDrafter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Drafting takes longer than a shared host lets a page load run, so it is
 * queued (the background runner picks it up) and the page polls for it.
 */
class DraftMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 150;

    public function __construct(public int $draftId)
    {
        $this->onQueue('high');
    }

    public function handle(MessageDrafter $drafter): void
    {
        $draft = MessageDraft::query()->find($this->draftId);

        if ($draft && $draft->status === 'queued') {
            // The runner's pass is short; a draft may need a minute or two.
            @set_time_limit(240);

            $drafter->draft($draft);
        }
    }

    public function failed(): void
    {
        MessageDraft::query()->whereKey($this->draftId)->where('status', 'queued')
            ->update(['status' => 'failed', 'error' => 'Drafting stopped unexpectedly. Try again.', 'updated_at' => now()]);
    }
}
