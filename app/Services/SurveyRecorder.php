<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Models\Ward;
use App\Support\Phone;
use App\Support\Time;
use Illuminate\Validation\ValidationException;

/**
 * Stores one survey response from any channel (field, web, SMS, USSD),
 * checking the answers against the questions. One response per phone per
 * survey: the phone is kept only as a keyed hash, never the number.
 */
class SurveyRecorder
{
    /**
     * @param  array<int|string, mixed>  $answers  question id → value (string, or list for multiple choice)
     * @param  array{uuid: string, channel: string, ward_id?: ?int, phone?: ?string, gender?: ?string, age_band?: ?string, occupation?: ?string, collected_by?: ?User, answered_at?: ?string}  $meta
     */
    public function record(Survey $survey, array $answers, array $meta): SurveyResponse
    {
        if ($existing = SurveyResponse::query()->where('uuid', $meta['uuid'])->first()) {
            return $existing;
        }

        if ($survey->status !== Survey::LIVE) {
            throw ValidationException::withMessages(['survey' => 'This survey is closed.']);
        }
        if (! $survey->hasChannel($meta['channel'] === 'ussd' ? 'sms' : $meta['channel'])) {
            throw ValidationException::withMessages(['survey' => 'This survey doesn’t take answers this way.']);
        }

        $ward = filled($meta['ward_id'] ?? null) ? Ward::query()->find($meta['ward_id']) : null;
        if ($ward && ! $survey->targets($ward)) {
            throw ValidationException::withMessages(['ward_id' => 'This survey isn’t running in that ward.']);
        }

        $hash = filled($meta['phone'] ?? null) ? Phone::hash($meta['phone']) : null;
        if (filled($meta['phone'] ?? null) && $hash === null) {
            throw ValidationException::withMessages(['phone' => 'Enter a Nigerian mobile number, e.g. 0803 123 4567.']);
        }
        if ($hash && SurveyResponse::query()->where('survey_id', $survey->id)->where('phone_hash', $hash)->exists()) {
            throw ValidationException::withMessages(['phone' => 'This phone number has already answered this survey.']);
        }

        // An SMS answers one question and USSD skips free text: required
        // questions apply to field and web answers.
        $clean = $this->answers($survey, $answers, in_array($meta['channel'], ['sms', 'ussd'], true));
        $time = Time::parse($meta['answered_at'] ?? null);

        return SurveyResponse::create([
            'uuid' => $meta['uuid'],
            'survey_id' => $survey->id,
            'channel' => $meta['channel'],
            'lga_id' => $ward?->lga_id,
            'ward_id' => $ward?->id,
            'gender' => array_key_exists($meta['gender'] ?? '', config('canvass.genders')) ? $meta['gender'] : null,
            'age_band' => array_key_exists($meta['age_band'] ?? '', config('canvass.age_bands')) ? $meta['age_band'] : null,
            'occupation' => array_key_exists($meta['occupation'] ?? '', config('canvass.occupations')) ? $meta['occupation'] : null,
            'phone_hash' => $hash,
            'collected_by' => ($meta['collected_by'] ?? null)?->id,
            'answers' => $clean,
            'answered_at' => $time && $time->lte(now()->addMinutes(10)) && $time->gte(now()->subDays(60)) ? $time : now(),
        ]);
    }

    /**
     * @param  array<int|string, mixed>  $answers
     * @return array<string, string|list<string>>
     */
    private function answers(Survey $survey, array $answers, bool $partial = false): array
    {
        $clean = [];
        $errors = [];

        foreach ($survey->questions as $question) {
            $value = $answers[$question->id] ?? $answers[(string) $question->id] ?? null;
            $choices = $question->choices();

            if ($value === null || $value === '' || $value === []) {
                $question->required && ! $partial && $errors["answers.{$question->id}"] = "Answer: {$question->prompt}";

                continue;
            }

            if ($question->type === 'text') {
                $clean[(string) $question->id] = mb_substr(trim((string) $value), 0, 500);
            } elseif ($question->type === 'multiple') {
                $picked = array_values(array_intersect(array_map('strval', (array) $value), array_keys($choices)));
                $picked ? $clean[(string) $question->id] = $picked : $errors["answers.{$question->id}"] = "Choose from the list: {$question->prompt}";
            } elseif (array_key_exists((string) $value, $choices)) {
                $clean[(string) $question->id] = (string) $value;
            } else {
                $errors["answers.{$question->id}"] = "Choose from the list: {$question->prompt}";
            }
        }

        if ($errors === [] && $clean === []) {
            $errors['answers'] = 'Answer at least one question.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }
}
