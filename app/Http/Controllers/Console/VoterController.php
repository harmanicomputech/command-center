<?php

namespace App\Http\Controllers\Console;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Voter;
use App\Models\Ward;
use App\Support\Audit;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Registrations in the user's area: spot-check calls, verification and
 * resolving possible duplicates. Coordinators and above see phone numbers
 * in full; exports are admin-only and audit-logged.
 */
class VoterController extends Controller
{
    public const FILTERS = [
        'all' => 'All',
        'unverified' => 'Unverified',
        'duplicates' => 'Possible duplicates',
        'verified' => 'Verified',
        'invalid' => 'Invalid',
        'spot-check' => 'Spot-check',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = array_key_exists((string) $request->query('filter'), self::FILTERS) ? (string) $request->query('filter') : 'all';
        $q = trim((string) $request->query('q'));
        $wardId = $request->integer('ward') ?: null;

        $base = Voter::query()->inAreaOf($user)->whereNull('erased_at');
        $query = (clone $base)->with('ward.lga', 'agent', 'original.agent')
            ->when($wardId, fn (Builder $query) => $query->where('voters.ward_id', $wardId))
            ->when($q !== '', fn (Builder $query) => $this->search($query, $q));

        match ($filter) {
            'unverified' => $query->where('status', Voter::UNVERIFIED)->where('possible_duplicate', false),
            'duplicates' => $query->where('possible_duplicate', true),
            'verified' => $query->where('status', Voter::VERIFIED),
            'invalid' => $query->where('status', Voter::INVALID),
            // A fresh random sample of unverified records to call today.
            'spot-check' => $query->where('status', Voter::UNVERIFIED)->where('possible_duplicate', false)->inRandomOrder(crc32(now()->format('Y-m-d').$user->id)),
            default => null,
        };

        $voters = $filter === 'spot-check'
            ? $query->limit(10)->get()
            : $query->latest('captured_at')->paginate(30)->withQueryString();

        return view('voters.index', [
            'voters' => $voters,
            'filter' => $filter,
            'q' => $q,
            'wardId' => $wardId,
            'wards' => Ward::query()->visibleTo($user)->with('lga')->orderBy('name')->get(),
            'counts' => [
                'all' => (clone $base)->count(),
                'unverified' => (clone $base)->where('status', Voter::UNVERIFIED)->where('possible_duplicate', false)->count(),
                'duplicates' => (clone $base)->where('possible_duplicate', true)->count(),
                'verified' => (clone $base)->where('status', Voter::VERIFIED)->count(),
                'invalid' => (clone $base)->where('status', Voter::INVALID)->count(),
            ],
            'canVerify' => $this->canVerify($user),
        ]);
    }

    public function verify(Request $request, Voter $voter): RedirectResponse
    {
        $this->authorizeVerify($request->user(), $voter);

        $voter->update(['status' => Voter::VERIFIED, 'verified_by' => $request->user()->id, 'verified_at' => now(), 'verification_note' => null]);

        return back()->with('status', "{$voter->name} is verified.");
    }

    public function invalidate(Request $request, Voter $voter): RedirectResponse
    {
        $this->authorizeVerify($request->user(), $voter);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null;

        $voter->update(['status' => Voter::INVALID, 'verified_by' => $request->user()->id, 'verified_at' => now(), 'verification_note' => $note ?: 'Could not confirm on a call']);
        Audit::record('voters.invalid', "Marked registration #{$voter->id} invalid", ['voter_id' => $voter->id, 'agent_id' => $voter->captured_by]);

        return back()->with('status', "{$voter->name} is marked invalid. It no longer counts for the agent.");
    }

    /**
     * A possible duplicate: the same person (this record is invalid) or
     * different people who share a phone (both count).
     */
    public function resolve(Request $request, Voter $voter): RedirectResponse
    {
        $this->authorizeVerify($request->user(), $voter);
        $same = $request->validate(['decision' => ['required', 'in:same,different']])['decision'] === 'same';

        $voter->update($same
            ? ['possible_duplicate' => false, 'status' => Voter::INVALID, 'verified_by' => $request->user()->id, 'verified_at' => now(), 'verification_note' => 'Duplicate of #'.$voter->duplicate_of]
            : ['possible_duplicate' => false]);
        Audit::record('voters.duplicate', ($same ? 'Confirmed duplicate' : 'Cleared possible duplicate')." #{$voter->id}", ['voter_id' => $voter->id]);

        return back()->with('status', $same ? 'Marked as a duplicate. It doesn’t count.' : 'Kept as a separate voter.');
    }

    /**
     * CSV of the registrations in view (admins only; audit-logged with the
     * row count).
     */
    public function export(Request $request): StreamedResponse
    {
        $query = Voter::query()->with('ward.lga', 'agent')->whereNull('erased_at')->orderBy('id');
        $rows = (clone $query)->count();
        Audit::record('voters.export', "Exported {$rows} registrations", rows: $rows);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'name', 'phone', 'gender', 'age_band', 'occupation', 'lga', 'ward', 'community', 'support_level', 'top_issue', 'status', 'possible_duplicate', 'captured_at', 'agent', 'consent_version'], escape: '\\');
            $query->chunk(500, function ($voters) use ($out) {
                foreach ($voters as $voter) {
                    fputcsv($out, [
                        $voter->id, $voter->name, $voter->phone, $voter->gender, $voter->age_band, $voter->occupation,
                        $voter->ward?->lga?->name, $voter->ward?->name, $voter->community, $voter->support_level, $voter->top_issue,
                        $voter->status, $voter->possible_duplicate ? 'yes' : 'no', $voter->captured_at?->toIso8601String(), $voter->agent?->name, $voter->consent_version,
                    ], escape: '\\');
                }
            });
            fclose($out);
        }, 'registrations-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Name contains, or an exact phone number (by its hash, since numbers
     * are encrypted).
     */
    private function search(Builder $query, string $q): Builder
    {
        $digits = Phone::searchDigits($q);
        $hash = strlen($digits) >= 10 ? Phone::hash('0'.$digits) : null;

        return $query->where(fn (Builder $inner) => $inner->where('voters.name', 'like', '%'.$q.'%')
            ->when($hash, fn (Builder $or) => $or->orWhere('voters.phone_hash', $hash)));
    }

    private function canVerify(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::LgaLeader, UserRole::WardCoordinator);
    }

    private function authorizeVerify(User $user, Voter $voter): void
    {
        abort_unless($this->canVerify($user) && $user->canSeeWard($voter->ward), 403, 'You can’t verify registrations outside your area.');
    }
}
