<x-layouts.app :title="$lga->name">
    <x-page-header :title="$lga->name" eyebrow="LGA" :back="route('areas')"
        description="{{ $lga->wards_count }} wards · {{ number_format($lga->polling_units_count) }} polling units · {{ number_format($lga->registered_voters) }} registered voters">
        <x-slot:actions>
            @forelse ($leaders as $leader)
                <span class="flex items-center gap-2 rounded-full border border-line bg-surface py-1 pr-3 pl-1 text-sm font-medium shadow-xs"><x-avatar :name="$leader->name" :size="26" />{{ $leader->name }}</span>
            @empty
                <x-badge tone="warn" icon="triangle-alert">No LGA leader yet</x-badge>
            @endforelse
        </x-slot:actions>
    </x-page-header>

    <x-table title="Wards">
        <thead>
            <tr>
                <th>Ward</th>
                <th>Coordinator</th>
                <th class="n">Agents</th>
                <th class="n">Polling units</th>
                <th class="n">Registered voters</th>
                <th class="hidden w-40 md:table-cell"><span class="sr-only">Share of the largest</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($wards as $ward)
                <tr>
                    <td><a href="{{ route('areas.ward', [$lga, $ward->slug]) }}" class="font-semibold hover:text-brand-fg">{{ $ward->name }}</a></td>
                    <td>
                        @if ($coordinators[$ward->id] ?? 0)
                            <x-badge tone="good" dot>Yes</x-badge>
                        @else
                            <x-badge tone="bad" dot>None</x-badge>
                        @endif
                    </td>
                    <td class="n">{{ $agents[$ward->id] ?? 0 }}</td>
                    <td class="n">{{ $ward->polling_units_count }}</td>
                    <td class="n font-medium">{{ number_format($ward->registered_voters) }}</td>
                    <td class="hidden md:table-cell"><x-progress :value="$ward->registered_voters" :max="$maxRegistered" /></td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty icon="map-pin" title="No wards in your area here" compact /></td></tr>
            @endforelse
        </tbody>
    </x-table>
</x-layouts.app>
