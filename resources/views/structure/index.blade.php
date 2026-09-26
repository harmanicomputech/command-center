<x-layouts.app title="Structure health">
    <x-page-header title="Structure health" eyebrow="Field"
        description="Every ward needs a coordinator and steady activity. A ward is red with no coordinator, or nothing registered or held there for {{ config('structure.quiet_ward_days') }} days.">
        <x-slot:actions>
            <form method="get" action="{{ route('structure') }}">
                <select name="lga" class="input w-56" aria-label="LGA" onchange="this.form.submit()">
                    <option value="">All LGAs in your area</option>
                    @foreach ($lgas as $option)
                        <option value="{{ $option->slug }}" @selected($lga?->id === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </form>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-kpi label="Wards flagged red" :value="$summary['red']" icon="triangle-alert" :hint="'of '.$summary['wards'].' wards'" />
        <x-kpi label="Without a coordinator" :value="$summary['noCoordinator']" icon="user-plus" />
        <x-kpi label="Field agents" :value="$summary['agents']" icon="users" />
        <x-kpi label="Active agents" :value="$summary['agents'] ? round(100 * $summary['active'] / $summary['agents']) : 0" suffix="%" icon="activity" :hint="$summary['active'].' active in 14 days'" />
    </div>

    <x-table class="mt-6" title="Wards" description="Red first, then by LGA.">
        <thead>
            <tr><th>Ward</th><th>Status</th><th class="n">Coordinators</th><th class="n">Active agents</th><th class="hidden md:table-cell">Last meeting</th><th class="hidden lg:table-cell">Last activity</th></tr>
        </thead>
        <tbody>
            @forelse ($health as $row)
                <tr>
                    <td class="whitespace-nowrap">
                        <a href="{{ route('areas.ward', [$row['ward']->lga, $row['ward']->slug]) }}" class="font-semibold hover:text-brand-fg">{{ $row['ward']->name }}</a>
                        <p class="text-xs text-subtle">{{ $row['ward']->lga->name }}</p>
                    </td>
                    <td>
                        @if ($row['red'])
                            <div class="flex flex-wrap gap-1">
                                @foreach ($row['reasons'] as $reason)<x-badge tone="bad" dot>{{ $reason }}</x-badge>@endforeach
                            </div>
                        @else
                            <x-badge tone="good" dot>Healthy</x-badge>
                        @endif
                    </td>
                    <td class="n">{{ $row['coordinators'] }}</td>
                    <td class="n">{{ $row['active'] }}<span class="text-subtle"> / {{ $row['agents'] }}</span></td>
                    <td class="hidden whitespace-nowrap text-muted md:table-cell">{{ $row['last_meeting'] ? \App\Support\Time::local($row['last_meeting'], 'j M') : 'None' }}</td>
                    <td class="hidden whitespace-nowrap text-muted lg:table-cell">{{ $row['last_activity']?->diffForHumans() ?? 'None' }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty icon="map" title="No wards in your area" compact /></td></tr>
            @endforelse
        </tbody>
    </x-table>
</x-layouts.app>
