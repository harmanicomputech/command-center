<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Anything that stops an AI answer: not set up, over budget, refused, or
 * the API failing. The message is safe to show to staff.
 */
class AiException extends RuntimeException
{
    /** @var array{input: int, output: int, cache_read: int, cache_write: int}|null */
    public ?array $usage = null;

    public ?string $model = null;

    public string $status = 'error';
}
