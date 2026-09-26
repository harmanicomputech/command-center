<?php

namespace App\Services\Ai;

/**
 * A model that answers with JSON matching a schema. The real one is Claude
 * (AnthropicModel); tests swap in a fake.
 */
interface LanguageModel
{
    /**
     * @param  list<array{text: string, cache?: bool}>  $system  stable blocks first; `cache` marks the end of the cached prefix
     * @param  array<string, mixed>  $schema  JSON schema of the answer
     * @return array{data: array<string, mixed>, model: string, usage: array{input: int, output: int, cache_read: int, cache_write: int}}
     *
     * @throws AiException
     */
    public function json(array $system, string $prompt, array $schema, int $maxTokens): array;
}
