<?php

namespace App\Services;

use App\Models\User;
use App\Models\Voter;
use App\Support\Phone;
use Illuminate\Support\Collection;

/**
 * Patterns worth a coordinator's second look before points count: many
 * registrations in minutes, runs of consecutive phone numbers, or every
 * voter a "strong supporter". Shown to coordinators and above, never
 * publicly, and a flag is a prompt to call, not a verdict.
 *
 * @phpstan-type Flag array{user: User, kind: string, detail: string}
 */
class Review
{
    /**
     * @return Collection<int, array{user: User, kind: string, detail: string}>
     */
    public function flags(User $viewer): Collection
    {
        $recent = Voter::query()->inAreaOf($viewer)->counted()->where('captured_at', '>=', now()->subDays(30))
            ->whereNotNull('captured_by')->get(['id', 'captured_by', 'captured_at', 'support_level', 'phone'])
            ->groupBy('captured_by');

        $users = User::query()->whereIn('id', $recent->keys())->with('ward')->get()->keyBy('id');
        $flags = collect();

        foreach ($recent as $userId => $voters) {
            $user = $users[$userId] ?? null;
            if (! $user) {
                continue;
            }

            if ($burst = $this->largestBurst($voters->where('captured_at', '>=', now()->subDays(7))->pluck('captured_at')->map->timestamp->sort()->values()->all())) {
                $flags->push(['user' => $user, 'kind' => 'burst', 'detail' => "{$burst} registrations within ".config('field.burst_minutes').' minutes']);
            }

            if ($run = $this->longestPhoneRun($voters->pluck('phone')->filter()->all())) {
                $flags->push(['user' => $user, 'kind' => 'phones', 'detail' => "{$run} phone numbers in a row (e.g. …01, …02, …03)"]);
            }

            if ($voters->count() >= (int) config('field.all_strong_min') && $voters->every(fn ($voter) => $voter->support_level === 'strong')) {
                $flags->push(['user' => $user, 'kind' => 'all_strong', 'detail' => "All {$voters->count()} voters this month are strong supporters"]);
            }
        }

        return $flags;
    }

    /**
     * The most registrations inside any window of burst_minutes, if it
     * reaches burst_count.
     *
     * @param  list<int>  $times  sorted timestamps
     */
    private function largestBurst(array $times): ?int
    {
        $window = 60 * (int) config('field.burst_minutes');
        $best = 0;
        $start = 0;

        foreach ($times as $end => $time) {
            while ($time - $times[$start] > $window) {
                $start++;
            }
            $best = max($best, $end - $start + 1);
        }

        return $best >= (int) config('field.burst_count') ? $best : null;
    }

    /**
     * The longest run of consecutive numbers (0803…001, …002, …003), if 4+.
     *
     * @param  list<string>  $phones
     */
    private function longestPhoneRun(array $phones): ?int
    {
        $numbers = collect($phones)->map(fn ($phone) => (int) substr((string) Phone::normalize($phone), -9))->filter()->unique()->sort()->values();
        $best = $numbers->isEmpty() ? 0 : 1;
        $run = 1;

        for ($i = 1; $i < $numbers->count(); $i++) {
            $run = $numbers[$i] === $numbers[$i - 1] + 1 ? $run + 1 : 1;
            $best = max($best, $run);
        }

        return $best >= 4 ? $best : null;
    }
}
