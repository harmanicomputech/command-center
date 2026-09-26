<?php

namespace App\Services\Field;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Applies a batch from the Field Force outbox, one result per item:
 *
 *   ok      stored (or already stored): the phone removes it from its outbox
 *   invalid can never be stored as sent (the phone shows why, to fix)
 *   error   a server problem: the phone keeps it and retries later
 */
class FieldSync
{
    /** @var array<string, class-string<SyncHandler>> */
    public const HANDLERS = [
        'voter' => RegisterVoter::class,
        'task_report' => ReportTask::class,
        'issue' => ReportIssue::class,
        'survey_response' => AnswerSurvey::class,
        'narrative_report' => ReportNarrative::class,
    ];

    public const MAX_ITEMS = 50;

    /**
     * @param  list<array{id?: mixed, type?: mixed, payload?: mixed}>  $items
     * @return list<array{id: string, status: string, message?: string, errors?: array<string, list<string>>, data?: array<string, mixed>}>
     */
    public function apply(User $user, array $items): array
    {
        $results = [];

        foreach (array_slice($items, 0, self::MAX_ITEMS) as $item) {
            $id = (string) ($item['id'] ?? '');
            $handler = self::HANDLERS[$item['type'] ?? ''] ?? null;

            if (! Str::isUuid($id)) {
                $results[] = ['id' => $id, 'status' => 'invalid', 'message' => 'This item has no valid ID.'];

                continue;
            }

            if ($handler === null || ! is_array($item['payload'] ?? null)) {
                $results[] = ['id' => $id, 'status' => 'invalid', 'message' => 'This app version sent something the server doesn’t know. Update the app.'];

                continue;
            }

            try {
                $results[] = ['id' => $id, ...app($handler)->apply($user, strtolower($id), $item['payload'])];
            } catch (ValidationException $e) {
                $results[] = ['id' => $id, 'status' => 'invalid', 'message' => collect($e->errors())->flatten()->first(), 'errors' => $e->errors()];
            } catch (Throwable $e) {
                report($e);
                $results[] = ['id' => $id, 'status' => 'error', 'message' => 'The server couldn’t save this yet. It will try again.'];
            }
        }

        return $results;
    }
}
