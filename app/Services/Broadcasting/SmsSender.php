<?php

namespace App\Services\Broadcasting;

use App\Models\Broadcast;
use App\Models\BroadcastMessage;
use App\Support\Phone;
use App\Support\Secrets;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Africa's Talking bulk SMS (from Election Shield): one request per batch,
 * each recipient reported back with its own status and message id (matched
 * to the delivery reports later). Numbers are decrypted only here.
 */
class SmsSender
{
    private const LIVE_URL = 'https://api.africastalking.com/version1/messaging';

    private const SANDBOX_URL = 'https://api.sandbox.africastalking.com/version1/messaging';

    /** Africa's Talking recipient codes for an accepted message. */
    private const ACCEPTED = [100, 101, 102];

    public static function configured(): bool
    {
        return filled(Secrets::get('africastalking_api_key')) && filled(Secrets::get('africastalking_username'));
    }

    /**
     * @param  Collection<int, BroadcastMessage>  $messages
     */
    public function send(Broadcast $broadcast, Collection $messages): void
    {
        if (! self::configured()) {
            throw new RuntimeException('Add the Africa’s Talking username and API key on the System page to send SMS.');
        }

        $phones = $messages->mapWithKeys(fn (BroadcastMessage $message) => [$message->id => Phone::normalize($message->phone())]);

        foreach ($messages->filter(fn ($message) => $phones[$message->id] === null) as $message) {
            $message->forceFill(['status' => 'failed', 'failure_reason' => 'No number, or they opted out', 'sent_at' => now()])->save();
        }

        $sendable = $messages->filter(fn ($message) => $phones[$message->id] !== null);
        if ($sendable->isEmpty()) {
            return;
        }

        $username = (string) Secrets::get('africastalking_username');
        $response = Http::asForm()
            ->acceptJson()
            ->withHeaders(['apiKey' => (string) Secrets::get('africastalking_api_key')])
            ->timeout(30)
            ->post($username === 'sandbox' ? self::SANDBOX_URL : self::LIVE_URL, array_filter([
                'username' => $username,
                'to' => $sendable->map(fn ($message) => $phones[$message->id])->unique()->implode(','),
                'message' => $broadcast->message,
                'from' => Secrets::get('africastalking_sender_id'),
                'bulkSMSMode' => 1,
                'enqueue' => 1,
            ]))
            ->throw()
            ->json('SMSMessageData.Recipients', []);

        $byPhone = collect($response)->keyBy('number');

        foreach ($sendable as $message) {
            $recipient = $byPhone->get($phones[$message->id]);
            $accepted = $recipient && in_array((int) ($recipient['statusCode'] ?? 0), self::ACCEPTED, true);

            $message->forceFill([
                'status' => $accepted ? 'sent' : 'failed',
                'provider_id' => $recipient['messageId'] ?? null,
                'cost' => $recipient['cost'] ?? null,
                'failure_reason' => $accepted ? null : mb_substr((string) ($recipient['status'] ?? 'Not in the provider response'), 0, 250),
                'sent_at' => now(),
            ])->save();
        }
    }
}
