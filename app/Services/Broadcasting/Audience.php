<?php

namespace App\Services\Broadcasting;

use App\Enums\UserRole;
use App\Models\Lga;
use App\Models\SmsOptOut;
use App\Models\User;
use App\Services\Segments;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who a broadcast goes to (adapted from Election Shield). Consent rules:
 *  - voters only with a phone number, consent on record (every canvassed
 *    voter agreed to be contacted) and no opt-out; one message per number;
 *  - team members get operational SMS as part of their role;
 *  - anyone whose number replied STOP is always left out.
 *
 * Audience: {"type": "voters", "filters": {segment filters}}
 *        or {"type": "team", "roles": ["agent", ...], "lga_id": [..]}
 */
class Audience
{
    public const TEAM_ROLES = ['agent', 'ward_coordinator', 'lga_leader', 'strategist', 'admin'];

    public function __construct(private Segments $segments) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function clean(array $input): array
    {
        if (($input['type'] ?? 'voters') === 'team') {
            $roles = array_values(array_intersect((array) ($input['roles'] ?? []), self::TEAM_ROLES));
            $lgas = array_values(array_filter(array_map('intval', (array) ($input['lga_id'] ?? []))));

            return ['type' => 'team', 'roles' => $roles ?: ['agent'], 'lga_id' => $lgas];
        }

        return ['type' => 'voters', 'filters' => $this->segments->clean((array) ($input['filters'] ?? []))];
    }

    public function label(array $audience): string
    {
        if ($audience['type'] === 'team') {
            $roles = collect($audience['roles'])->map(fn ($role) => UserRole::from($role)->label())->implode(', ');
            $lgas = $audience['lga_id'] ? Lga::query()->whereIn('id', $audience['lga_id'])->orderBy('name')->pluck('name')->implode(', ') : 'all LGAs';

            return "Team: {$roles} ({$lgas})";
        }

        return 'Voters: '.$this->segments->label($audience['filters']);
    }

    /**
     * Recipients as [type, id], one per phone number.
     *
     * @return Collection<int, array{0: string, 1: int}>
     */
    public function recipients(array $audience, User $viewer): Collection
    {
        if ($audience['type'] === 'team') {
            $optedOut = SmsOptOut::query()->pluck('phone_hash')->flip();

            return User::query()
                ->whereIn('role', $audience['roles'])
                ->whereNull('disabled_at')
                ->whereNotNull('phone')
                ->when($audience['lga_id'], fn (Builder $query, $lgas) => $query->where(fn (Builder $query) => $query
                    ->whereIn('lga_id', $lgas)->orWhereHas('ward', fn (Builder $ward) => $ward->whereIn('lga_id', $lgas))))
                ->orderBy('id')->get(['id', 'phone'])
                ->map(fn (User $user) => ['id' => $user->id, 'hash' => Phone::hash($user->phone)])
                ->filter(fn ($row) => $row['hash'] !== null && ! $optedOut->has($row['hash']))
                ->unique('hash')
                ->map(fn ($row) => ['user', $row['id']])->values();
        }

        return $this->voters($audience['filters'], $viewer)
            ->selectRaw('min(voters.id) as id')->groupBy('voters.phone_hash')->pluck('id')
            ->sort()->values()->map(fn ($id) => ['voter', (int) $id]);
    }

    public function count(array $audience, User $viewer): int
    {
        if ($audience['type'] === 'team') {
            return $this->recipients($audience, $viewer)->count();
        }

        return $this->voters($audience['filters'], $viewer)->distinct()->count('voters.phone_hash');
    }

    private function voters(array $filters, User $viewer): Builder
    {
        return $this->segments->query($viewer, $filters)
            ->whereNotNull('voters.phone_hash')
            ->whereNull('voters.opted_out_at')
            ->whereNotNull('voters.consent_at')
            ->whereNotIn('voters.phone_hash', SmsOptOut::query()->select('phone_hash'));
    }
}
