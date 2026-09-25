<?php

namespace App\Http\Controllers;

use App\Models\PollingUnit;
use App\Models\Voter;
use App\Models\Ward;
use App\Services\Field\AgentStats;
use App\Services\Field\RegisterVoter;
use App\Support\Settings;
use App\Support\Time;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The Field Force app (phone). Forms save to the outbox on the phone first
 * (public/outbox.js) and reach the server through /api/field/sync; the
 * POST routes here are the fallback when scripts don't run.
 */
class FieldController extends Controller
{
    public function home(Request $request, AgentStats $stats): View
    {
        $user = $request->user();

        return view('field.home', [
            'user' => $user,
            'greeting' => Time::greeting(),
            'stats' => $stats->for($user),
            'target' => Settings::int('target.agent_daily'),
            'recent' => Voter::query()->where('captured_by', $user->id)->latest('captured_at')->limit(3)->get(),
        ]);
    }

    public function register(Request $request): View
    {
        $user = $request->user();
        $lgaId = $user->lga_id ?? $user->ward?->lga_id;

        // The agent's LGA (voters near a ward boundary); staff their area.
        $wards = Ward::query()
            ->when($lgaId && ! $user->role->isStatewide(), fn ($query) => $query->where('lga_id', $lgaId))
            ->with('lga')->orderBy('name')->get();

        $units = PollingUnit::query()->whereIn('ward_id', $wards->pluck('id'))->orderBy('code')->get(['id', 'ward_id', 'code', 'name'])
            ->groupBy('ward_id')
            ->map(fn ($units) => $units->map(fn (PollingUnit $unit) => ['id' => $unit->id, 'name' => ($unit->name ?? $unit->inecCode()).' · '.substr($unit->code, -3)])->values());

        return view('field.register', [
            'wards' => $wards->map(fn (Ward $ward) => ['id' => $ward->id, 'name' => $user->role->isStatewide() ? $ward->fullName() : $ward->name])->values()->all(),
            'units' => $units->all(),
            'defaultWard' => $user->ward_id ?? $wards->first()?->id,
            'points' => ['unverified' => Settings::int('points.registration_unverified'), 'verified' => Settings::int('points.registration_verified')],
        ]);
    }

    /**
     * Without scripts the form posts here; the same handler as the sync.
     */
    public function store(Request $request, RegisterVoter $handler): RedirectResponse
    {
        $uuid = Str::isUuid((string) $request->input('uuid')) ? strtolower($request->input('uuid')) : (string) Str::uuid();
        $handler->apply($request->user(), $uuid, [...$request->except('_token', 'uuid'), 'consent' => $request->boolean('consent')]);

        return redirect()->route('field.register')->with('status', 'Saved. '.$request->input('name').' is registered.');
    }

    public function outbox(): View
    {
        return view('field.outbox');
    }

    /**
     * The agent's own registrations. Phone numbers are masked once synced.
     */
    public function registrations(Request $request): View
    {
        return view('field.registrations', [
            'voters' => Voter::query()->where('captured_by', $request->user()->id)->with('ward')->latest('captured_at')->paginate(30),
        ]);
    }

    public function me(Request $request, AgentStats $stats): View
    {
        return view('field.me', ['user' => $request->user(), 'stats' => $stats->for($request->user())]);
    }

    public function tasks(): View
    {
        return view('field.soon', ['page' => 'tasks']);
    }

    public function issues(): View
    {
        return view('field.soon', ['page' => 'issues']);
    }
}
