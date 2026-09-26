<?php

namespace App\Services;

use App\Models\Lga;
use App\Models\Volunteer;
use App\Models\Ward;
use App\Support\Phone;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Records a volunteer sign-up from the /join page or the campaign website.
 * Consent is required; the same number signing up again updates its row.
 */
class VolunteerIntake
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function record(array $input, string $source): Volunteer
    {
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'lga' => ['required', 'max:60'],
            'ward' => ['nullable', 'max:120'],
            'help' => ['nullable', 'array'],
            'help.*' => [Rule::in(array_keys(config('volunteers.help')))],
            'message' => ['nullable', 'string', 'max:500'],
            'consent' => ['accepted'],
        ], [
            'name.required' => 'Tell us your name.',
            'lga.required' => 'Choose your LGA.',
            'consent.accepted' => 'Tick the box so we can contact you.',
        ])->validate();

        $phone = Phone::normalize($data['phone']);
        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => 'Enter a Nigerian mobile number, e.g. 0803 123 4567.']);
        }

        // The LGA and ward may come as ids (our form) or names (the website).
        $data['lga'] = (string) $data['lga'];
        $lga = Lga::query()->whereKey(is_numeric($data['lga']) ? (int) $data['lga'] : 0)
            ->orWhere('slug', Str::slug($data['lga']))->orWhere('name', $data['lga'])->first();
        if (! $lga) {
            throw ValidationException::withMessages(['lga' => 'Choose one of Ebonyi’s 13 LGAs.']);
        }
        $ward = filled($data['ward'] ?? null)
            ? Ward::query()->where('lga_id', $lga->id)->where(fn ($query) => $query->whereKey(is_numeric($data['ward']) ? (int) $data['ward'] : 0)->orWhere('name', $data['ward']))->first()
            : null;

        $hash = Phone::hash($phone);
        $volunteer = Volunteer::query()->firstOrNew(['phone_hash' => $hash]);
        $volunteer->fill([
            'name' => trim($data['name']),
            'phone' => $phone,
            'lga_id' => $lga->id,
            'ward_id' => $ward?->id,
            'help' => array_values(array_unique($data['help'] ?? [])) ?: null,
            'message' => filled($data['message'] ?? null) ? trim($data['message']) : null,
            'source' => $volunteer->source ?? $source,
            'consent_at' => now(),
            'consent_version' => (string) config('volunteers.consent_version'),
        ]);
        if ($volunteer->exists && $volunteer->status === 'not_now') {
            $volunteer->status = 'new';
        }
        $volunteer->save();

        return $volunteer;
    }
}
