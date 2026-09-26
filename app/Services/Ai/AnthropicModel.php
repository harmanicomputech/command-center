<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Support\Secrets;
use Psr\Http\Client\ClientInterface;

/**
 * Claude through the official Anthropic PHP SDK (anthropic-ai/sdk, shipped
 * in vendor/). Structured output keeps answers machine-readable; adaptive
 * thinking is on; the stable system blocks carry a cache breakpoint so the
 * policy brief is billed at the cache rate after the first draft.
 */
class AnthropicModel implements LanguageModel
{
    public function __construct(private ?ClientInterface $transporter = null) {}

    public function json(array $system, string $prompt, array $schema, int $maxTokens): array
    {
        $key = Secrets::get('anthropic_api_key');
        if (blank($key)) {
            throw new AiException('Add the Claude API key on the System page (or ANTHROPIC_API_KEY in .env) to use AI drafting.');
        }

        $client = new Client(apiKey: $key, requestOptions: array_filter([
            'timeout' => (float) config('messaging.ai.timeout'),
            'maxRetries' => 1,
            'transporter' => $this->transporter,
        ]));

        try {
            $message = $client->beta->messages->create(
                maxTokens: $maxTokens,
                model: (string) config('messaging.ai.model'),
                system: array_map(fn (array $block) => ['type' => 'text', 'text' => $block['text']]
                    + (($block['cache'] ?? false) ? ['cacheControl' => ['type' => 'ephemeral']] : []), $system),
                messages: [['role' => 'user', 'content' => $prompt]],
                thinking: ['type' => 'adaptive'],
                outputConfig: ['format' => ['type' => 'json_schema', 'schema' => $schema]],
                // If the model declines on policy grounds, the API retries
                // on its default fallback model inside the same call.
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AuthenticationException) {
            throw new AiException('Claude rejected the API key. Check it on the System page.');
        } catch (RateLimitException) {
            throw new AiException('Claude is busy (rate limit). Try again in a minute.');
        } catch (APIStatusException $e) {
            throw new AiException('Claude returned an error ('.($e->status ?? '?').'). Try again later.');
        } catch (APIConnectionException) {
            throw new AiException('Couldn’t reach Claude. Check the server’s internet connection and try again.');
        } catch (APIException $e) {
            throw new AiException('Claude request failed: '.mb_substr($e->getMessage(), 0, 150));
        }

        $usage = [
            'input' => (int) $message->usage->inputTokens,
            'output' => (int) $message->usage->outputTokens,
            'cache_read' => (int) ($message->usage->cacheReadInputTokens ?? 0),
            'cache_write' => (int) ($message->usage->cacheCreationInputTokens ?? 0),
        ];

        $text = null;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text = $block->text;
                break;
            }
        }

        $data = match (true) {
            $message->stopReason === 'refusal' => $this->fail('Claude declined to write this. Rephrase the goal and try again.', $usage, $message->model, 'refused'),
            $message->stopReason === 'max_tokens' => $this->fail('The answer was cut off. Try a shorter goal.', $usage, $message->model),
            default => json_decode((string) $text, true),
        };

        if (! is_array($data)) {
            $this->fail('Claude’s answer couldn’t be read. Try again.', $usage, $message->model);
        }

        return ['data' => $data, 'model' => (string) $message->model, 'usage' => $usage];
    }

    /**
     * @param  array{input: int, output: int, cache_read: int, cache_write: int}  $usage
     */
    private function fail(string $message, array $usage, string $model, string $status = 'error'): never
    {
        $e = new AiException($message);
        $e->usage = $usage;
        $e->model = $model;
        $e->status = $status;

        throw $e;
    }
}
