<x-layouts.app title="Registrations">
    <x-page-header title="Registrations" eyebrow="Field"
        description="Voters the field has registered in your area. Call a sample to verify them: invalid records lose the agent’s points, and possible duplicates don’t count until someone decides.">
        <x-slot:actions>
            @if (auth()->user()->isAdmin())
                <x-button :href="route('voters.export')" variant="secondary" icon="download">Export CSV</x-button>
            @endif
            @if ($canVerify)
                <x-button :href="route('voters', ['filter' => 'spot-check'])" icon="phone">Spot-check 10</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @php
        $tabs = collect(\App\Http\Controllers\Console\VoterController::FILTERS)->except('spot-check')
            ->map(fn ($label, $key) => [$label, route('voters', array_filter(['filter' => $key === 'all' ? null : $key, 'q' => $q ?: null, 'ward' => $wardId])), $filter === $key, number_format($counts[$key])])
            ->values()->all();
        if ($filter === 'spot-check') {
            $tabs[] = ['Spot-check', route('voters', ['filter' => 'spot-check']), true];
        }
    @endphp
    <x-tabs :items="$tabs" />

    <form method="get" action="{{ route('voters') }}" class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_240px_auto]">
        @if ($filter !== 'all')<input type="hidden" name="filter" value="{{ $filter }}">@endif
        <x-input name="q" :value="$q" icon="search" placeholder="Name, or a full phone number" aria-label="Search registrations" />
        <select name="ward" class="input" aria-label="Ward" onchange="this.form.submit()">
            <option value="">All wards in your area</option>
            @foreach ($wards as $ward)
                <option value="{{ $ward->id }}" @selected($wardId === $ward->id)>{{ $ward->fullName() }}</option>
            @endforeach
        </select>
        <x-button variant="secondary" icon="funnel">Filter</x-button>
    </form>

    @if ($filter === 'spot-check')
        <x-alert tone="info" title="Today’s spot-check" class="mb-4">A random sample of 10 unverified registrations. Call each voter, confirm their name and that they agreed, then mark them.</x-alert>
    @endif

    <div class="card divide-y divide-line overflow-hidden">
        @forelse ($voters as $voter)
            <article class="grid grid-cols-1 gap-3 p-4 sm:p-5 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_auto] lg:items-center">
                <div class="flex min-w-0 items-start gap-3">
                    <x-avatar :name="$voter->name ?? '?'" :size="40" />
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ $voter->name }}</p>
                        <p class="text-sm text-muted">
                            @if ($voter->phone)
                                <a href="tel:{{ $voter->phone }}" class="num font-medium text-brand-fg hover:underline">{{ $voter->phoneForStaff() }}</a>
                            @else
                                <span class="text-subtle">No phone</span>
                            @endif
                            · {{ config('canvass.genders.'.$voter->gender) }}, {{ config('canvass.age_bands.'.$voter->age_band) }} · {{ config('canvass.occupations.'.$voter->occupation) }}
                        </p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <x-badge :tone="$voter->supportTone()" dot>{{ $voter->supportLabel() }}</x-badge>
                            @if ($voter->top_issue)<x-badge>{{ config('canvass.issues.'.$voter->top_issue) }}</x-badge>@endif
                            @include('field._status', ['voter' => $voter])
                        </div>
                    </div>
                </div>
                <div class="min-w-0 text-sm text-muted lg:text-[13px]">
                    <p class="truncate"><x-icon name="map-pin" size="14" class="mr-1 inline -translate-y-px" />{{ $voter->ward?->fullName() }}{{ $voter->community ? ' · '.$voter->community : '' }}</p>
                    <p class="truncate"><x-icon name="user" size="14" class="mr-1 inline -translate-y-px" />{{ $voter->agent?->name ?? 'Unknown agent' }} · {{ \App\Support\Time::local($voter->captured_at, 'j M, g:i A') }}</p>
                    @if ($voter->verification_note)<p class="truncate text-subtle">Note: {{ $voter->verification_note }}</p>@endif
                </div>
                @if ($canVerify)
                    <div class="flex flex-wrap gap-2 lg:justify-end">
                        @if ($voter->possible_duplicate)
                            <p class="w-full text-sm lg:text-right">Same phone as <strong>{{ $voter->original?->name ?? 'a deleted record' }}</strong>{{ $voter->original?->agent ? ' ('.$voter->original->agent->name.')' : '' }}</p>
                            <form method="post" action="{{ route('voters.resolve', $voter) }}">@csrf<input type="hidden" name="decision" value="same"><x-button size="sm" variant="secondary" icon="copy">Same person</x-button></form>
                            <form method="post" action="{{ route('voters.resolve', $voter) }}">@csrf<input type="hidden" name="decision" value="different"><x-button size="sm" variant="secondary" icon="users">Different people</x-button></form>
                        @else
                            @if ($voter->status !== 'verified')
                                <form method="post" action="{{ route('voters.verify', $voter) }}">@csrf<x-button size="sm" variant="soft" icon="badge-check">Verify</x-button></form>
                            @endif
                            @if ($voter->status !== 'invalid')
                                <form method="post" action="{{ route('voters.invalidate', $voter) }}" x-data x-on:submit="const note = prompt('Why is it invalid? (optional)'); if (note === null) { $event.preventDefault(); } else { $el.note.value = note; }">
                                    @csrf<input type="hidden" name="note">
                                    <x-button size="sm" variant="ghost" icon="x">Invalid</x-button>
                                </form>
                            @endif
                        @endif
                    </div>
                @endif
            </article>
        @empty
            <x-empty icon="user-round-check" title="{{ $q !== '' || $wardId ? 'No registrations match' : 'No registrations here yet' }}"
                description="{{ $q !== '' || $wardId ? 'Try another name or ward.' : 'When agents register voters on their phones, they appear here within seconds of syncing.' }}">
                @if (Route::has('team') && $q === '' && ! $wardId)
                    <x-button :href="route('team')" icon="user-plus" variant="secondary">Invite agents</x-button>
                @endif
            </x-empty>
        @endforelse
    </div>
    @if ($voters instanceof \Illuminate\Contracts\Pagination\Paginator && $voters->hasPages())
        <div class="mt-4">{{ $voters->links() }}</div>
    @endif
</x-layouts.app>
