<?php

namespace App\Services\Field;

use App\Models\Survey;
use App\Models\User;
use App\Services\SurveyRecorder;
use Illuminate\Validation\ValidationException;

/**
 * A survey response collected by an agent in the field (offline first).
 */
class AnswerSurvey implements SyncHandler
{
    public function __construct(private SurveyRecorder $recorder) {}

    public function apply(User $user, string $uuid, array $payload): array
    {
        $survey = Survey::query()->with('questions')->find($payload['survey_id'] ?? 0);
        if (! $survey) {
            throw ValidationException::withMessages(['survey' => 'This survey no longer exists.']);
        }

        $this->recorder->record($survey, (array) ($payload['answers'] ?? []), [
            'uuid' => $uuid,
            'channel' => 'field',
            'ward_id' => $payload['ward_id'] ?? $user->ward_id,
            'phone' => $payload['phone'] ?? null,
            'gender' => $payload['gender'] ?? null,
            'age_band' => $payload['age_band'] ?? null,
            'occupation' => $payload['occupation'] ?? null,
            'collected_by' => $user,
            'answered_at' => $payload['answered_at'] ?? null,
        ]);

        return ['status' => 'ok'];
    }
}
