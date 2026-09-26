<?php

namespace App\Services\Field;

use App\Models\Issue;
use App\Models\User;
use App\Models\Ward;
use App\Services\PushAlerts;
use App\Support\Time;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A community issue reported from the field (the photo follows separately).
 */
class ReportIssue implements SyncHandler
{
    public function apply(User $user, string $uuid, array $payload): array
    {
        if (Issue::query()->where('uuid', $uuid)->exists()) {
            return ['status' => 'ok', 'message' => 'Already saved.'];
        }

        $data = Validator::make($payload, [
            'category' => ['required', Rule::in(array_keys(config('field.issue_categories')))],
            'description' => ['required', 'string', 'min:5', 'max:2000'],
            'severity' => ['required', Rule::in(array_keys(config('field.severities')))],
            'people_affected' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'ward_id' => ['required', 'integer'],
            'community' => ['nullable', 'string', 'max:120'],
            'reported_at' => ['nullable', 'string', 'max:40'],
        ], ['description.required' => 'Describe the issue in a sentence or two.'])->validate();

        $ward = Ward::query()->find($data['ward_id']);
        $allowed = $ward && ($user->role->isStatewide() || $user->lga_id === $ward->lga_id || $user->canSeeWard($ward));
        if (! $allowed) {
            throw ValidationException::withMessages(['ward_id' => 'Choose a ward in your LGA.']);
        }

        $time = Time::parse($data['reported_at'] ?? null);

        $issue = Issue::create([
            'uuid' => $uuid,
            'category' => $data['category'],
            'description' => trim($data['description']),
            'severity' => $data['severity'],
            'people_affected' => $data['people_affected'] ?? null,
            'lga_id' => $ward->lga_id,
            'ward_id' => $ward->id,
            'community' => filled($data['community'] ?? null) ? trim($data['community']) : null,
            'reported_by' => $user->id,
            'reported_at' => $time && $time->lte(now()->addMinutes(10)) && $time->gte(now()->subDays(60)) ? $time : now(),
        ]);

        // Security reports alert the LGA's leaders (only fresh ones, so a
        // backlog synced days later doesn't set off alarms).
        if ($issue->category === 'security' && $issue->reported_at->gte(now()->subMinutes(30))) {
            PushAlerts::queue('security', [
                'title' => 'Security issue in '.$ward->name.', '.$ward->lga->name,
                'body' => Str::limit($issue->description, 120),
                'url' => '/issues?category=security',
                'tag' => 'security-'.$issue->id,
                'urgent' => in_array($issue->severity, ['high', 'critical'], true),
            ], $ward->lga_id);
        }

        return ['status' => 'ok'];
    }
}
