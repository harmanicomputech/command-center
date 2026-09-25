<?php

namespace Tests\Concerns;

use App\Models\User;
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

        return $this->actingAs($agent)->postJson('/api/field/sync', ['items' => $items]);
    }
}
