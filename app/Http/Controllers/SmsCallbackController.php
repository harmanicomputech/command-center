<?php

namespace App\Http\Controllers;

use App\Models\BroadcastMessage;
use App\Models\SmsOptOut;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Africa's Talking callbacks (from Election Shield): delivery reports,
 * bulk-SMS opt-outs, and incoming SMS where "STOP" opts the number out.
 * The URLs carry a token derived from the app key (shown on the System page).
 */
class SmsCallbackController extends Controller
{
    public const STOP_WORDS = ['STOP', 'STOP ALL', 'UNSUBSCRIBE', 'END', 'QUIT', 'KWUSI'];

    public static function token(): string
    {
        return substr(hash_hmac('sha256', 'command-center-sms', (string) config('app.key')), 0, 32);
    }

    public function delivery(Request $request, string $token): JsonResponse
    {
        $this->check($token);

        $message = BroadcastMessage::query()->where('provider_id', (string) $request->input('id'))->first();

        if ($message) {
            $status = (string) $request->input('status');

            match ($status) {
                'Success' => $message->forceFill(['status' => 'delivered', 'delivered_at' => now(), 'failure_reason' => null])->save(),
                'Failed', 'Rejected', 'AbsentSubscriber', 'Expired' => $message->forceFill(['status' => 'failed', 'failure_reason' => Str::limit($request->input('failureReason') ?: $status, 250)])->save(),
                default => null, // Sent, Submitted, Buffered: still on its way
            };
        }

        return response()->json(['status' => 'ok']);
    }

    public function optOut(Request $request, string $token): JsonResponse
    {
        $this->check($token);
        SmsOptOut::record((string) $request->input('phoneNumber'), 'africastalking');

        return response()->json(['status' => 'ok']);
    }

    public function inbox(Request $request, string $token): JsonResponse
    {
        $this->check($token);
        $text = strtoupper(trim(preg_replace('/\s+/', ' ', (string) $request->input('text')) ?? ''));

        if (in_array($text, self::STOP_WORDS, true)) {
            SmsOptOut::record((string) $request->input('from'), 'sms reply');
        }

        return response()->json(['status' => 'ok']);
    }

    private function check(string $token): void
    {
        abort_unless(hash_equals(self::token(), $token), 404);
    }
}
