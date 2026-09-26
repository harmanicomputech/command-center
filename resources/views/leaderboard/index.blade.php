@php
    $link = fn (array $change) => route('leaderboard', [...['level' => $level, 'period' => $period], ...$change]);
    $isAgents = $level === 'agents';
@endphp
<x-layouts.app title="Leaderboard">
    <x-page-header title="Leaderboard" eyebrow="Field" :description="$scopeLabel.' · '.($period === 'week' ? 'this week, since Monday '.$weekOf->format('j M').' (it starts again every Monday)' : 'all time')">
        <x-slot:actions>
            @if (auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::LgaLeader, \App\Enums\UserRole::WardCoordinator))
                <x-button :href="route('leaderboard.review')" variant="secondary" icon="shield-check">Review patterns</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-1 rounded-[10px] bg-surface-2 p-1" role="tablist">
            @foreach (['agents' => 'Agents', 'wards' => 'Wards', 'lgas' => 'LGAs'] as $key => $label)
                <a href="{{ $link(['level' => $key]) }}" role="tab" aria-selected="{{ $level === $key ? 'true' : 'false' }}" class="h-8 rounded-lg px-3 text-[13px] leading-8 font-semibold {{ $level === $key ? 'bg-surface text-ink shadow-xs' : 'text-muted hover:text-ink' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="flex gap-1 rounded-[10px] bg-surface-2 p-1" role="tablist">
            @foreach (['week' => 'This week', 'all' => 'All time'] as $key => $label)
                <a href="{{ $link(['period' => $key]) }}" role="tab" aria-selected="{{ $period === $key ? 'true' : 'false' }}" class="h-8 rounded-lg px-3 text-[13px] leading-8 font-semibold {{ $period === $key ? 'bg-surface text-ink shadow-xs' : 'text-muted hover:text-ink' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div>
            @if ($rows->sum('points') > 0)
                <div class="card p-6"><x-podium :rows="$rows" class="mx-auto max-w-md" /></div>
                <x-table class="mt-6">
                    <thead>
                        <tr>
                            <th class="w-12">#</th><th>{{ $isAgents ? 'Agent' : ($level === 'wards' ? 'Ward' : 'LGA') }}</th>
                            @if ($isAgents)
                                <th class="n">Registered</th><th class="n hidden sm:table-cell">Verified</th><th class="n hidden md:table-cell">Tasks</th><th class="n hidden md:table-cell">Issues</th><th class="n hidden lg:table-cell">Events</th>
                            @else
                                <th class="n">Agents</th>
                            @endif
                            <th class="n">Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows->take(100) as $row)
                            <tr>
                                <td class="num font-bold text-muted">{{ $row['rank'] }}</td>
                                <td>
                                    <p class="font-semibold whitespace-nowrap">{{ $isAgents ? $row['user']->name : $row['name'] }}</p>
                                    <p class="text-xs text-subtle">{{ $isAgents ? $row['user']->ward?->name : $row['detail'] }}</p>
                                </td>
                                @if ($isAgents)
                                    <td class="n">{{ number_format($row['registrations']) }}</td>
                                    <td class="n hidden sm:table-cell">{{ number_format($row['verified']) }}</td>
                                    <td class="n hidden md:table-cell">{{ $row['tasks'] }}</td>
                                    <td class="n hidden md:table-cell">{{ $row['issues'] }}</td>
                                    <td class="n hidden lg:table-cell">{{ $row['events'] }}</td>
                                @else
                                    <td class="n">{{ $row['agents'] }}</td>
                                @endif
                                <td class="n font-bold">{{ number_format($row['points']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            @else
                <div class="card"><x-empty icon="trophy" title="No points {{ $period === 'week' ? 'yet this week' : 'yet' }}" description="Points come from registrations (more once verified), finished tasks, accepted issue reports and events attended." /></div>
            @endif
        </div>

        <div class="space-y-6">
            <x-card title="How points work" icon="star">
                <dl class="divide-y divide-line text-sm">
                    @foreach (\App\Support\SettingsRegistry::groups()['points']['fields'] as $key => $field)
                        <div class="flex justify-between gap-3 py-2 first:pt-0"><dt class="text-muted">{{ $field['label'] }}</dt><dd class="num font-semibold">{{ \App\Support\Settings::int($key) }}</dd></div>
                    @endforeach
                </dl>
                <p class="mt-3 text-xs text-subtle">Invalid registrations and unresolved duplicates earn nothing.</p>
            </x-card>
            <x-card title="Rewards" description="Given to last week’s winners." icon="medal">
                @forelse ($rewards as $reward)
                    <div class="flex items-center gap-3 border-b border-line py-2.5 text-sm first:pt-0 last:border-0">
                        <x-avatar :name="$reward->user->name" :size="30" />
                        <span class="min-w-0 flex-1"><span class="block truncate font-medium">{{ $reward->user->name }}</span><span class="block truncate text-xs text-subtle">{{ $reward->kindLabel() }}{{ $reward->description ? ' · '.$reward->description : '' }} · week of {{ $reward->week_of->format('j M') }}</span></span>
                    </div>
                @empty
                    <p class="text-sm text-muted">No rewards recorded yet.</p>
                @endforelse
                @if ($canReward)
                    <form method="post" action="{{ route('leaderboard.reward') }}" class="mt-4 space-y-3 border-t border-line pt-4">
                        @csrf
                        <p class="text-sm font-semibold">Record a reward</p>
                        <select name="user_id" class="input" aria-label="Who" required>
                            @foreach ($isAgents ? $rows->take(10) : collect() as $row)<option value="{{ $row['user']->id }}">{{ $row['rank'] }}. {{ $row['user']->name }}</option>@endforeach
                        </select>
                        <select name="kind" class="input" aria-label="Reward">@foreach (config('field.reward_kinds') as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                        <input name="description" class="input" placeholder="e.g. ₦1,000 airtime" aria-label="Details">
                        <x-button variant="secondary" size="sm" icon="check" class="w-full">Record reward</x-button>
                    </form>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.app>
