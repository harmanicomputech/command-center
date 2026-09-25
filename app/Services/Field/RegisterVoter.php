<?php

namespace App\Services\Field;

use App\Models\PollingUnit;
use App\Models\User;
use App\Models\Voter;
use App\Models\Ward;
use App\Support\Phone;
use App\Support\Settings;
use App\Support\Time;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A voter registration from the field form (through the outbox, or posted
 * directly when scripts are off). No consent, no record.
 */
class RegisterVoter implements SyncHandler
{
    public function apply(User $user, string $uuid, array $payload): array
    {
        if ($existing = Voter::query()->where('uuid', $uuid)->first()) {
            return ['status' => 'ok', 'message' => 'Already saved.', 'data' => $this->summary($existing)];
        }

        $data = Validator::make($payload, [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['required', Rule::in(array_keys(config('canvass.genders')))],
            'age_band' => ['required', Rule::in(array_keys(config('canvass.age_bands')))],
            'occupation' => ['required', Rule::in(array_keys(config('canvass.occupations')))],
            'ward_id' => ['required', 'integer'],
            'polling_unit_id' => ['nullable', 'integer'],
            'community' => ['nullable', 'string', 'max:120'],
            'support_level' => ['required', Rule::in(array_keys(config('canvass.support_levels')))],
            'top_issue' => ['nullable', Rule::in(array_keys(config('canvass.issues')))],
            'notes' => ['nullable', 'string', 'max:500'],
            'consent' => ['accepted'],
            'captured_at' => ['nullable', 'string', 'max:40'],
            'latitude' => ['nullable', 'numeric', 'between:4,7.5'],
            'longitude' => ['nullable', 'numeric', 'between:6.5,9'],
        ], [
            'consent.accepted' => 'The voter must agree before we can save their details.',
            'name.required' => 'Enter the voter’s name.',
            'support_level.required' => 'Choose how they feel about us.',
        ], ['age_band' => 'age band', 'ward_id' => 'ward'])->validate();

        $phone = filled($data['phone'] ?? null) ? Phone::normalize($data['phone']) : null;
        if (filled($data['phone'] ?? null) && $phone === null) {
            throw ValidationException::withMessages(['phone' => 'Enter a Nigerian mobile number, e.g. 0803 123 4567.']);
        }

        $ward = Ward::query()->find($data['ward_id']);
        if (! $ward || ! $this->mayRegisterIn($user, $ward)) {
            throw ValidationException::withMessages(['ward_id' => 'Choose a ward in your LGA.']);
        }

        $unit = filled($data['polling_unit_id'] ?? null) ? PollingUnit::query()->where('ward_id', $ward->id)->find($data['polling_unit_id']) : null;

        return DB::transaction(function () use ($user, $uuid, $data, $phone, $ward, $unit) {
            $hash = $phone ? Phone::hash($phone) : null;
            $original = $hash ? Voter::query()->where('phone_hash', $hash)->whereNull('erased_at')->orderBy('id')->first() : null;

            $voter = Voter::create([
                'uuid' => $uuid,
                'name' => trim($data['name']),
                'phone' => $phone,
                'phone_hash' => $hash,
                'gender' => $data['gender'],
                'age_band' => $data['age_band'],
                'occupation' => $data['occupation'],
                'lga_id' => $ward->lga_id,
                'ward_id' => $ward->id,
                'polling_unit_id' => $unit?->id,
                'community' => filled($data['community'] ?? null) ? trim($data['community']) : null,
                'support_level' => $data['support_level'],
                'top_issue' => $data['top_issue'] ?? null,
                'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
                'consent_at' => $this->capturedAt($data['captured_at'] ?? null),
                'consent_version' => (string) config('canvass.consent_version'),
                'captured_by' => $user->id,
                'captured_at' => $this->capturedAt($data['captured_at'] ?? null),
                'latitude' => isset($data['latitude']) ? round((float) $data['latitude'], 3) : null,
                'longitude' => isset($data['longitude']) ? round((float) $data['longitude'], 3) : null,
                'duplicate_of' => $original?->id,
                'possible_duplicate' => $original !== null,
            ]);

            return ['status' => 'ok', 'data' => $this->summary($voter)];
        });
    }

    /**
     * Agents and coordinators register in their own LGA (voters near a ward
     * boundary); leaders in their area.
     */
    private function mayRegisterIn(User $user, Ward $ward): bool
    {
        return $user->role->isStatewide() || ($user->lga_id !== null && $user->lga_id === $ward->lga_id) || $user->canSeeWard($ward);
    }

    /**
     * The phone's clock, unless it is clearly wrong (in the future, or
     * months back): then the time it reached the server.
     */
    private function capturedAt(?string $value): Carbon
    {
        $time = Time::parse($value);

        return $time && $time->lte(now()->addMinutes(10)) && $time->gte(now()->subDays(60)) ? $time : now();
    }

    /**
     * @return array{uuid: string, duplicate: bool, points: int}
     */
    private function summary(Voter $voter): array
    {
        return [
            'uuid' => $voter->uuid,
            'duplicate' => $voter->possible_duplicate,
            'points' => $voter->possible_duplicate ? 0 : Settings::int('points.registration_unverified'),
        ];
    }
}
