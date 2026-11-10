<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Models\Voter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait RegistersVoters
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function voterPayload(int $wardId, array $overrides = []): array
    {
        return [
            'name' => 'Test Voter',
            'phone' => '0800 000 1001',
            'gender' => 'female',
            'age_band' => '25-34',
            'occupation' => 'farmer',
            'ward_id' => $wardId,
            'support_level' => 'leaning',
            'top_issue' => 'roads',
            'consent' => true,
            'captured_at' => now()->toIso8601String(),
            'face_check' => ['status' => 'passed', 'challenges' => ['smile', 'turn', 'blink'], 'duration_ms' => 14000, 'failures' => 0, 'camera' => 'environment'],
            ...$overrides,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $payloads  [uuid => payload] or a list
     */
    protected function syncVoters(User $agent, array $payloads): TestResponse
    {
        $items = [];
        foreach ($payloads as $key => $payload) {
            $items[] = ['id' => is_string($key) ? $key : (string) Str::uuid(), 'type' => 'voter', 'payload' => $payload];
        }

        $response = $this->actingAs($agent)->postJson('/api/field/sync', ['items' => $items]);

        // As on a phone, each passed face check's photo follows its record
        // (FaceCheckTest sends real photos).
        Voter::query()->whereIn('uuid', array_map(fn ($item) => strtolower($item['id']), $items))->where('face_status', 'passed')->update(['face_photo_at' => now()]);

        return $response;
    }
}
