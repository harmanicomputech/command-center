<?php

namespace App\Services\Field;

use App\Models\NarrativeReport;
use App\Models\User;
use App\Models\Ward;
use App\Support\Time;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "What people are saying", reported from the field (the screenshot or
 * photo follows separately). The media team groups reports into narratives.
 */
class ReportNarrative implements SyncHandler
{
    public function apply(User $user, string $uuid, array $payload): array
    {
        if (NarrativeReport::query()->where('uuid', $uuid)->exists()) {
            return ['status' => 'ok', 'message' => 'Already saved.'];
        }

        $data = Validator::make($payload, [
            'source' => ['required', Rule::in(array_keys(config('messaging.sources')))],
            'summary' => ['required', 'string', 'min:5', 'max:2000'],
            'topic' => ['required', Rule::in(array_keys(config('messaging.narrative_topics')))],
            'tone' => ['required', Rule::in(array_keys(config('messaging.narrative_tones')))],
            'link' => ['nullable', 'url:http,https', 'max:500'],
            'ward_id' => ['required', 'integer'],
            'seen_at' => ['nullable', 'string', 'max:40'],
        ], [
            'summary.required' => 'Say in a sentence what people are saying.',
            'link.url' => 'Paste the full link, starting with https://',
        ])->validate();

        $ward = Ward::query()->find($data['ward_id']);
        $allowed = $ward && ($user->role->isStatewide() || $user->lga_id === $ward->lga_id || $user->canSeeWard($ward));
        if (! $allowed) {
            throw ValidationException::withMessages(['ward_id' => 'Choose a ward in your LGA.']);
        }

        $time = Time::parse($data['seen_at'] ?? null);

        NarrativeReport::create([
            'uuid' => $uuid,
            'source' => $data['source'],
            'summary' => trim($data['summary']),
            'topic' => $data['topic'],
            'tone' => $data['tone'],
            'link' => $data['link'] ?? null,
            'lga_id' => $ward->lga_id,
            'ward_id' => $ward->id,
            'reported_by' => $user->id,
            'seen_at' => $time && $time->lte(now()->addMinutes(10)) && $time->gte(now()->subDays(60)) ? $time : now(),
        ]);

        return ['status' => 'ok'];
    }
}
