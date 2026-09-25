<x-layouts.app title="Map & wards">
    <x-page-header title="Map & wards" eyebrow="Intelligence"
        description="Ebonyi’s LGAs and wards from the polling unit register. Zones, registrations and issues join the map as the field reports in." />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
        <x-lga-map :map="$map" class="xl:col-span-3" description="Tap an LGA to see its wards." />

        <div class="grid grid-cols-2 content-start gap-4 xl:col-span-2">
            <div class="card col-span-2 p-5">
                <p class="text-sm font-medium text-muted">Registered voters in your area</p>
                <p class="num mt-2 text-4xl font-bold tracking-tight"><span x-data x-count="{{ $totals['registered'] }}">{{ number_format($totals['registered']) }}</span></p>
                <p class="mt-2 text-xs text-subtle">From the bundled register. See the data warning on the System page before relying on it.</p>
            </div>
            <div class="card p-5"><x-stat label="Wards" :value="number_format($totals['wards'])" /></div>
            <div class="card p-5"><x-stat label="Polling units" :value="number_format($totals['units'])" /></div>
        </div>
    </div>

    <x-table class="mt-6" title="LGAs" description="{{ $lgas->count() }} {{ $lgas->count() === 1 ? 'LGA' : 'LGAs' }} in your area">
        <thead>
            <tr>
                <th>LGA</th>
                <th class="n">Wards</th>
                <th class="n">Coordinated</th>
                <th class="n">Polling units</th>
                <th class="n">Registered voters</th>
                <th class="hidden w-40 md:table-cell"><span class="sr-only">Share of the largest</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lgas as $lga)
                <tr>
                    <td><a href="{{ route('areas.lga', $lga) }}" class="font-semibold hover:text-brand-fg">{{ $lga->name }}</a></td>
                    <td class="n">{{ $lga->wards_count }}</td>
                    <td class="n">
                        @php($covered = $coordinators[$lga->id] ?? 0)
                        <span @class(['text-bad font-semibold' => $covered === 0])>{{ $covered }}</span><span class="text-subtle"> / {{ $lga->wards_count }}</span>
                    </td>
                    <td class="n">{{ number_format($lga->polling_units_count) }}</td>
                    <td class="n font-medium">{{ number_format($lga->registered_voters) }}</td>
                    <td class="hidden md:table-cell"><x-progress :value="$lga->registered_voters" :max="$maxRegistered" /></td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty icon="map" title="No areas yet" description="Load the polling unit register on the System page." compact /></td></tr>
            @endforelse
        </tbody>
    </x-table>
</x-layouts.app>
