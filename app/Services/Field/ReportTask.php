<?php

namespace App\Services\Field;

use App\Models\Task;
use App\Models\TaskReport;
use App\Models\User;
use App\Support\Time;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Progress on a task from the field: a count ("12 households"), a note,
 * and "done". The photo, if any, follows as its own outbox item.
 */
class ReportTask implements SyncHandler
{
    public function apply(User $user, string $uuid, array $payload): array
    {
        if (TaskReport::query()->where('uuid', $uuid)->exists()) {
            return ['status' => 'ok', 'message' => 'Already saved.'];
        }

        $data = Validator::make($payload, [
            'task_id' => ['required', 'integer'],
            'count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'done' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
            'has_photo' => ['nullable', 'boolean'],
            'reported_at' => ['nullable', 'string', 'max:40'],
        ])->validate();

        $task = Task::query()->for($user)->find($data['task_id']);
        if (! $task) {
            throw ValidationException::withMessages(['task_id' => 'This task is no longer assigned to you.']);
        }

        $done = (bool) ($data['done'] ?? false);
        $count = (int) ($data['count'] ?? 0);
        if ($done && $task->proof === 'photo' && ! ($data['has_photo'] ?? false)) {
            throw ValidationException::withMessages(['photo' => 'This task needs a photo as proof.']);
        }
        if ($done && $task->proof === 'count' && $count === 0 && $task->progressFor($user) === 0) {
            throw ValidationException::withMessages(['count' => 'Enter how many you did.']);
        }

        TaskReport::create([
            'uuid' => $uuid,
            'task_id' => $task->id,
            'user_id' => $user->id,
            'count' => $count,
            'done' => $done,
            'note' => filled($data['note'] ?? null) ? trim($data['note']) : null,
            'reported_at' => $this->reportedAt($data['reported_at'] ?? null),
        ]);

        return ['status' => 'ok'];
    }

    private function reportedAt(?string $value): Carbon
    {
        $time = Time::parse($value);

        return $time && $time->lte(now()->addMinutes(10)) && $time->gte(now()->subDays(60)) ? $time : now();
    }
}
