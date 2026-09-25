<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Lga;
use App\Models\User;
use App\Models\Ward;
use App\Support\Phone;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creating and changing accounts, shared by the admin Users page and (from
 * phase 2) coordinators' invites. Staff sign in by email and password;
 * field agents by phone and a 4–6 digit PIN.
 */
class UserDirectory
{
    /**
     * Validation rules for a new or changed account.
     *
     * @return array<string, mixed>
     */
    public function rules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'max:100'],
            'lga_id' => ['nullable', 'integer', 'exists:lgas,id'],
            'ward_id' => ['nullable', 'integer', 'exists:wards,id'],
        ];
    }

    /**
     * Normalise and check the role-specific parts, then return the
     * attributes to save.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function attributes(array $data, ?User $user = null): array
    {
        $role = UserRole::from($data['role']);
        $errors = [];
        $phone = filled($data['phone'] ?? null) ? Phone::normalize($data['phone']) : null;

        if (filled($data['phone'] ?? null) && $phone === null) {
            $errors['phone'] = 'Enter a Nigerian mobile number, e.g. 0803 123 4567.';
        } elseif ($phone && User::query()->where('phone', $phone)->when($user, fn ($q) => $q->whereKeyNot($user->id))->exists()) {
            $errors['phone'] = 'Someone already has this phone number.';
        }

        if ($role->usesFieldApp() && ! $phone) {
            $errors['phone'] ??= 'Field agents sign in with their phone number.';
        }

        if (! $role->usesFieldApp() && blank($data['email'] ?? null)) {
            $errors['email'] = 'Staff sign in with an email address.';
        }

        $password = $data['password'] ?? null;
        if (filled($password)) {
            if ($role->usesFieldApp() && ! preg_match('/^\d{4,6}$/', (string) $password)) {
                $errors['password'] = 'A PIN is 4 to 6 digits.';
            } elseif (! $role->usesFieldApp() && strlen((string) $password) < 10) {
                $errors['password'] = 'A password needs at least 10 characters.';
            }
        } elseif (! $user) {
            $errors['password'] = $role->usesFieldApp() ? 'Set a PIN of 4 to 6 digits.' : 'Set a password of at least 10 characters.';
        }

        $lgaId = null;
        $wardId = null;
        if ($role->needsLga()) {
            $lgaId = filled($data['lga_id'] ?? null) ? (int) $data['lga_id'] : null;
            $lgaId ?? $errors['lga_id'] = 'Choose the LGA this leader runs.';
        }

        if ($role->needsWard()) {
            $ward = filled($data['ward_id'] ?? null) ? Ward::query()->find($data['ward_id']) : null;

            if ($ward) {
                [$wardId, $lgaId] = [$ward->id, $ward->lga_id];
            } else {
                $errors['ward_id'] = 'Choose the ward.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return array_filter([
            'name' => trim($data['name']),
            'role' => $role,
            'email' => filled($data['email'] ?? null) ? strtolower(trim($data['email'])) : null,
            'phone' => $phone,
            'lga_id' => $lgaId,
            'ward_id' => $wardId,
            'password' => filled($password) ? $password : null,
        ], fn ($value, $key) => $value !== null || in_array($key, ['email', 'phone', 'lga_id', 'ward_id'], true), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Wards grouped by LGA, for selects.
     *
     * @return array<string, array<int, string>>
     */
    public static function wardOptions(?User $viewer = null): array
    {
        $query = Ward::query()->with('lga')->orderBy('name');
        $viewer && $query->visibleTo($viewer);

        return $query->get()->groupBy(fn (Ward $ward) => $ward->lga->name)->sortKeys()
            ->map(fn ($wards) => $wards->pluck('name', 'id')->all())->all();
    }

    /**
     * @return array<int, string>
     */
    public static function lgaOptions(): array
    {
        return Lga::query()->orderBy('name')->pluck('name', 'id')->all();
    }
}
