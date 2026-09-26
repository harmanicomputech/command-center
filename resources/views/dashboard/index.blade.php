@php
    $field = $brief['field'];
@endphp
<x-layouts.app title="Dashboard" wide>
    <x-page-header :title="$greeting.', '.auth()->user()->firstName()" :eyebrow="\App\Support\Time::now()->format('l j F')" description="Today’s picture: where we’re winning, where we’re losing, what to push next, and what the field did.">
        <x-slot:actions>
            <x-badge tone="accent" icon="calendar">{{ max(0, $daysToGo) }} days to go</x-badge>
            <x-button :href="route('brief')" variant="secondary" icon="printer">Daily brief</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($setup && collect($setup)->where('done', false)->isNotEmpty())
        <details class="card mb-6 overflow-hidden" @if (collect($setup)->where('done', true)->count() < 3) open @endif>
            <summary class="flex cursor-pointer list-none items-center gap-4 p-4 sm:px-6">
                <x-progress-ring :value="collect($setup)->where('done', true)->count()" :max="count($setup)" :size="44" :stroke="5" label="Set-up">
                    <span class="num text-xs font-bold">{{ collect($setup)->where('done', true)->count() }}/{{ count($setup) }}</span>
                </x-progress-ring>
                <span class="min-w-0 flex-1"><span class="block font-semibold">Finish setting up</span><span class="block text-sm text-muted">A few owner decisions make the figures below real.</span></span>
                <x-icon name="chevron-down" class="text-subtle" />
            </summary>
            <ul class="grid grid-cols-1 gap-1 border-t border-line p-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($setup as $step)
                    <li>
                        <a href="{{ $step['url'] }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors hover:bg-surface-2">
                            @if ($step['done'])
                                <span class="grid size-6 flex-none place-items-center rounded-full bg-good-soft text-good"><x-icon name="check" size="14" /></span><span class="flex-1 text-muted line-through decoration-line-strong">{{ $step['label'] }}</span>
                            @else
                                <span class="size-6 flex-none rounded-full border-[1.5px] border-dashed border-line-strong"></span><span class="flex-1 font-medium">{{ $step['label'] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif

    {{-- Hero KPIs --}}
    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-kpi class="rise" style="--i: 0" label="Registered this week" :value="$field['week']" :delta="$field['weekDelta']" :spark="$field['spark']" icon="user-round-check" :hint="$field['today'].' today'" :href="route('voters')" />
        <x-kpi class="rise" style="--i: 1" label="Canvassed in all" :value="$field['total']" icon="target" :hint="$field['target'] ? round(100 * $field['total'] / max(1, $field['target']), 1).'% of '.number_format($field['target']) : null" />
        <x-kpi class="rise" style="--i: 2" label="Agents active today" :value="$field['activeAgents']" icon="users" :hint="$field['tasksDone'].' tasks done · '.$field['issues'].' issues'" />
        <x-kpi class="rise" style="--i: 3" label="Wards with no activity" :value="$field['quietWards']" icon="heart-pulse" :hint="'of '.$field['wards'].' wards (7 days)'" :href="route('structure')" />
    </div>

    {{-- The map --}}
    <div class="mt-6">
        @if ($map['ward'])
            <x-ward-map :map="$map['ward']" title="Ebonyi by ward" />
        @else
            <x-lga-map :map="$map['lga']" title="Ebonyi by LGA" description="Zones, registrations, activity and open issues. The ward map appears once boundaries are uploaded (System page)." />
        @endif
    </div>

    {{-- Winning / losing / push next --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card title="Where we’re winning" description="Strongholds, with this week’s change in canvass support." icon="trending-up">
            @if ($brief['winning']->isEmpty())
                <p class="text-sm text-muted">No stronghold yet. {{ $brief['party'] ? '' : 'Set the party in Settings and import past results, or keep canvassing: zones appear once a ward has enough data.' }}</p>
            @else
                <ul class="divide-y divide-line">@foreach ($brief['winning'] as $row)@include('dashboard._zone-row', ['row' => $row])@endforeach</ul>
            @endif
        </x-card>
        <x-card title="Where we’re losing" description="Weak wards, and swing wards trending the wrong way." icon="trending-down">
            @if ($brief['losing']->isEmpty())
                <p class="text-sm text-muted">No weak or slipping wards on current data.</p>
            @else
                <ul class="divide-y divide-line">@foreach ($brief['losing'] as $row)@include('dashboard._zone-row', ['row' => $row])@endforeach</ul>
            @endif
        </x-card>
        <x-card title="What to push next" icon="sparkles">
            @if ($brief['suggestion'])
                <div class="rounded-xl bg-brand-softer p-4">
                    <p class="mb-1 flex items-center gap-1.5 text-xs font-semibold text-brand-fg"><x-icon name="sparkles" size="14" />AI suggestion, not a decision</p>
                    <p class="text-sm">{{ $brief['suggestion']['text'] }}</p>
                </div>
            @else
                <p class="rounded-xl bg-surface-2 p-3 text-sm text-muted">A daily AI suggestion appears here once AI drafting is set up. Until then, these are rising:</p>
            @endif
            <p class="eyebrow mt-4 mb-2">Rising issues this week</p>
            @forelse ($brief['rising'] as $issue)
                <a href="{{ route('issues', ['category' => $issue['category']]) }}" class="flex items-center justify-between gap-3 py-1.5 text-sm hover:text-brand-fg">
                    <span class="font-medium">{{ $issue['label'] }}</span>
                    <span class="num text-muted">{{ $issue['week'] }} <span class="text-xs">(▲ from {{ $issue['last'] }})</span></span>
                </a>
            @empty
                <p class="text-sm text-muted">Nothing rising this week.</p>
            @endforelse
        </x-card>
    </div>

    {{-- Priority and leaders --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-table title="Priority wards" description="Big, uncertain, reachable: registered voters × (1 − certainty) × reachability.">
            <x-slot:actions><x-button :href="route('areas')" variant="ghost" size="sm" icon-right="arrow-right">All wards</x-button></x-slot:actions>
            <thead><tr><th>Ward</th><th>Zone</th><th class="n">Priority</th></tr></thead>
            <tbody>
                @foreach ($brief['priority'] as $row)
                    <tr>
                        <td><a href="{{ route('areas.ward', [$row['lga_slug'], $row['slug']]) }}" class="font-semibold hover:text-brand-fg">{{ $row['name'] }}</a><p class="text-xs text-subtle">{{ $row['lga'] }}{{ $row['tag'] ? ' · '.$row['tag'] : '' }}</p></td>
                        <td><x-badge :tone="\App\Services\Intelligence::ZONES[$row['zone']]['tone']" dot>{{ \App\Services\Intelligence::ZONES[$row['zone']]['label'] }}</x-badge></td>
                        <td class="n font-semibold">{{ number_format($row['priority']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        <x-card title="Leaderboard this week" icon="trophy">
            <x-slot:actions><x-button :href="route('leaderboard')" variant="ghost" size="sm" icon-right="arrow-right">Full board</x-button></x-slot:actions>
            @if ($brief['leaders']['agents']->isEmpty())
                <x-empty icon="trophy" title="No points yet this week" description="The board starts again every Monday." compact />
            @else
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-accent-soft p-4"><p class="text-xs font-semibold text-accent-fg">Top LGA</p><p class="mt-1 truncate font-semibold">{{ $brief['leaders']['lga']['name'] ?? '—' }}</p><p class="num text-xs text-muted">{{ number_format($brief['leaders']['lga']['points'] ?? 0) }} pts</p></div>
                    <div class="rounded-xl bg-brand-softer p-4"><p class="text-xs font-semibold text-brand-fg">Top ward</p><p class="mt-1 truncate font-semibold">{{ $brief['leaders']['ward']['name'] ?? '—' }}</p><p class="num text-xs text-muted">{{ number_format($brief['leaders']['ward']['points'] ?? 0) }} pts</p></div>
                </div>
                <ol class="mt-4 divide-y divide-line">
                    @foreach ($brief['leaders']['agents'] as $row)
                        <li class="flex items-center gap-3 py-2.5 text-sm">
                            <span class="num w-5 font-bold text-muted">{{ $row['rank'] }}</span><x-avatar :name="$row['user']->name" :size="30" />
                            <span class="min-w-0 flex-1 truncate font-medium">{{ $row['user']->name }} <span class="text-subtle">· {{ $row['user']->ward?->name }}</span></span>
                            <span class="num font-semibold">{{ number_format($row['points']) }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-card>
    </div>
</x-layouts.app>
