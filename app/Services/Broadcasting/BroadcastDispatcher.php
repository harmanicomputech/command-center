<?php

namespace App\Services\Broadcasting;

use App\Jobs\SendBroadcastBatch;
use App\Models\Broadcast;
use App\Models\BroadcastMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Starts a broadcast: freezes its recipient list (so a voter registered
 * mid-send doesn't change it) and queues it in batches. From Election Shield.
 */
class BroadcastDispatcher
{
    public function __construct(private Audience $audience) {}

    public function start(Broadcast $broadcast, User $by): int
    {
        $count = DB::transaction(function () use ($broadcast, $by) {
            $broadcast = Broadcast::query()->lockForUpdate()->findOrFail($broadcast->id);

            if (! $broadcast->editable()) {
                return null;
            }

            $now = now();
            $rows = $this->audience->recipients($broadcast->audience, $by)
                ->map(fn (array $recipient) => ['broadcast_id' => $broadcast->id, 'recipient_type' => $recipient[0], 'recipient_id' => $recipient[1], 'status' => 'queued', 'created_at' => $now, 'updated_at' => $now]);

            $rows->chunk(500)->each(fn ($chunk) => BroadcastMessage::query()->insertOrIgnore($chunk->values()->all()));

            $broadcast->forceFill(['status' => Broadcast::SENDING, 'started_at' => $now, 'sent_by' => $by->id, 'recipients' => $rows->count()])->save();

            return $rows->count();
        });

        if ($count === null) {
            return 0;
        }

        $broadcast->messages()->where('status', 'queued')->orderBy('id')->pluck('id')
            ->chunk((int) config('messaging.sms_batch'))
            ->each(fn ($ids) => SendBroadcastBatch::dispatch($broadcast->id, $ids->values()->all()));

        // Reload: the status changed on the locked copy above. With no
        // recipients (or every batch already sent) it is finished now.
        $this->finishIfDone($broadcast->refresh());

        return $count;
    }

    public function finishIfDone(Broadcast $broadcast): void
    {
        if ($broadcast->status === Broadcast::SENDING && ! $broadcast->messages()->where('status', 'queued')->exists()) {
            $broadcast->forceFill(['status' => Broadcast::SENT, 'finished_at' => now()])->save();
        }
    }
}
