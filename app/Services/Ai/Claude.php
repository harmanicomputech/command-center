<?php

namespace App\Services\Ai;

use App\Models\AiCall;
use App\Models\User;
use App\Support\Secrets;
use App\Support\Settings;

/**
 * Every AI request goes through here: the monthly budget is checked first,
 * and each call (answered or failed) is logged with its tokens and cost.
 */
class Claude
{
    public function __construct(private LanguageModel $model) {}

    public static function configured(): bool
    {
        return filled(Secrets::get('anthropic_api_key'));
    }

    public static function budget(): float
    {
        return Settings::float('ai.monthly_budget');
    }

    /**
     * @param  list<array{text: string, cache?: bool}>  $system
     * @param  array<string, mixed>  $schema
     * @return array{data: array<string, mixed>, call: AiCall}
     *
     * @throws AiException
     */
    public function ask(string $purpose, array $system, string $prompt, array $schema, ?User $user = null, ?int $maxTokens = null): array
    {
        $budget = self::budget();
        if ($budget > 0 && AiCall::monthSpend() >= $budget) {
            throw new AiException(sprintf('This month’s AI budget ($%s) is used up. An admin can raise it in Settings.', number_format($budget, 2)));
        }

        $started = microtime(true);

        try {
            $answer = $this->model->json($system, $prompt, $schema, $maxTokens ?? (int) config('messaging.ai.max_tokens'));
        } catch (AiException $e) {
            $this->log($purpose, $e->model, $e->usage, $e->status, $e->getMessage(), $user, $started);

            throw $e;
        }

        $call = $this->log($purpose, $answer['model'], $answer['usage'], 'ok', null, $user, $started);

        return ['data' => $answer['data'], 'call' => $call];
    }

    /**
     * US dollars for a call's tokens: cache reads at 0.1× and cache writes
     * at 1.25× the input price.
     *
     * @param  array{input: int, output: int, cache_read: int, cache_write: int}  $usage
     */
    public static function cost(string $model, array $usage): float
    {
        [$in, $out] = config("messaging.ai.prices.{$model}") ?? config('messaging.ai.default_price');

        return ($usage['input'] * $in + $usage['output'] * $out + $usage['cache_read'] * $in * 0.1 + $usage['cache_write'] * $in * 1.25) / 1_000_000;
    }

    /**
     * @param  array{input: int, output: int, cache_read: int, cache_write: int}|null  $usage
     */
    private function log(string $purpose, ?string $model, ?array $usage, string $status, ?string $error, ?User $user, float $started): AiCall
    {
        $model ??= (string) config('messaging.ai.model');
        $usage ??= ['input' => 0, 'output' => 0, 'cache_read' => 0, 'cache_write' => 0];

        return AiCall::create([
            'purpose' => $purpose,
            'model' => $model,
            'status' => $status,
            'input_tokens' => $usage['input'],
            'output_tokens' => $usage['output'],
            'cache_read_tokens' => $usage['cache_read'],
            'cache_write_tokens' => $usage['cache_write'],
            'cost_usd' => round(self::cost($model, $usage), 6),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'error' => $error ? mb_substr($error, 0, 255) : null,
            'user_id' => $user?->id,
            'created_at' => now(),
        ]);
    }
}
