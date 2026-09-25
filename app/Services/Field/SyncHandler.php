<?php

namespace App\Services\Field;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * One kind of item the Field Force outbox sends (a registration, a task
 * update, an issue, a survey response). apply() must be idempotent on the
 * item's UUID: the same item can arrive more than once.
 */
interface SyncHandler
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: 'ok', message?: string, data?: array<string, mixed>}
     *
     * @throws ValidationException when the item can never be accepted as sent
     */
    public function apply(User $user, string $uuid, array $payload): array;
}
