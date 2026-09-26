<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\PushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Turning Web Push on or off per device, choosing topics, and a test.
 */
class PushController extends Controller
{
    public function show(Request $request, PushNotifier $notifier): View
    {
        return view('push.show', [
            'configured' => $notifier->configured(),
            'publicKey' => $notifier->publicKey(),
            'topics' => $this->topicsFor($request),
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:200'],
            'keys.auth' => ['required', 'string', 'max:100'],
            'contentEncoding' => ['nullable', 'string', 'max:20'],
            'topics' => ['array'],
            'topics.*' => [Rule::in(array_keys($this->topicsFor($request)))],
        ]);

        PushSubscription::query()->updateOrCreate(['endpoint_hash' => PushSubscription::hashEndpoint($data['endpoint'])], [
            'user_id' => $request->user()->id,
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
            'topics' => array_values($data['topics'] ?? array_keys($this->topicsFor($request))),
            'device' => substr((string) $request->userAgent(), 0, 250),
        ]);

        return response()->json(['status' => 'ok']);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $request->user()->hasMany(PushSubscription::class)
            ->where('endpoint_hash', PushSubscription::hashEndpoint((string) $request->input('endpoint')))->delete();

        return response()->json(['status' => 'ok']);
    }

    public function test(Request $request, PushNotifier $notifier): JsonResponse
    {
        $sent = $notifier->send(
            $request->user()->hasMany(PushSubscription::class)->get()->all(),
            ['title' => 'Notifications are on', 'body' => 'This device will get your alerts.', 'url' => '/', 'tag' => 'test'],
        );

        return response()->json(['sent' => $sent], $sent ? 200 : 422);
    }

    /**
     * Agents get task alerts; everyone else all topics.
     *
     * @return array<string, string>
     */
    private function topicsFor(Request $request): array
    {
        return $request->user()->role->usesFieldApp()
            ? array_intersect_key(PushSubscription::TOPICS, array_flip(['task_assigned']))
            : PushSubscription::TOPICS;
    }
}
