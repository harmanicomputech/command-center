<x-layouts.app title="Past results">
    <x-page-header title="Past results" eyebrow="Intelligence" :back="route('areas')"
        description="Governorship results per LGA or ward, the baseline for strongholds and swing areas. Only the latest year is used for zones; figures are never estimated.">
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card title="Import a year" icon="upload" class="lg:col-span-1">
            <form method="post" action="{{ route('results.import') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <x-select name="year" label="Election" :options="['2023' => '2023 governorship', '2019' => '2019 governorship', '2015' => '2015 governorship']" required />
                <div>
                    <label class="label" for="r-file">CSV file</label>
                    <input id="r-file" type="file" name="file" accept=".csv,text/csv" class="input" required>
                    <p class="hint">Columns: <code>lga, ward, party, votes</code>. Leave <code>ward</code> empty for LGA totals. Importing a year again replaces it.</p>
                </div>
                <x-button icon="upload" class="w-full">Import</x-button>
            </form>
            @if (! $party)
                <x-alert tone="warn" class="mt-4">Set the campaign’s party in <a href="{{ route('settings') }}" class="link">Settings</a>: our share is that party’s share.</x-alert>
            @endif
        </x-card>

        <div class="space-y-6 lg:col-span-2">
            <x-table title="Imported">
                <thead><tr><th>Year</th><th class="n">Rows</th><th class="n">Wards</th><th class="n">Votes</th><th></th></tr></thead>
                <tbody>
                    @forelse ($years as $year)
                        <tr>
                            <td class="font-semibold">{{ $year->year }} @if ($year->year === $latest)<x-badge tone="brand" class="ml-1">Used for zones</x-badge>@endif</td>
                            <td class="n">{{ number_format($year->n) }}</td>
                            <td class="n">{{ number_format($year->wards) }}</td>
                            <td class="n">{{ number_format($year->votes) }}</td>
                            <td class="text-right">
                                <form method="post" action="{{ route('results.destroy', $year->year) }}" x-data x-on:submit="if (! confirm('Remove the {{ $year->year }} results?')) $event.preventDefault()">@csrf @method('delete')<x-button variant="ghost" size="sm" icon="trash-2">Remove</x-button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty icon="vote" title="No results imported" description="Ask the campaign for the 2019 and 2023 governorship results per LGA and ward, as a CSV." compact /></td></tr>
                    @endforelse
                </tbody>
            </x-table>

            @if ($byLga->isNotEmpty())
                <x-table title="{{ $latest }} by LGA" description="Our share is {{ $party ?? 'the campaign’s party (not set)' }}.">
                    <thead><tr><th>LGA</th><th>Leading parties</th><th class="n">Our share</th></tr></thead>
                    <tbody>
                        @foreach ($byLga as $row)
                            <tr>
                                <td class="font-semibold">{{ $row['lga']->name }}</td>
                                <td>
                                    <div class="flex h-2.5 w-48 overflow-hidden rounded-full bg-surface-3" aria-hidden="true">
                                        @foreach ($row['parties']->take(4) as $name => $votes)
                                            <span style="width: {{ $row['total'] ? 100 * $votes / $row['total'] : 0 }}%; background: var(--party-{{ in_array(strtolower($name), ['apc', 'pdp', 'lp'], true) ? strtolower($name) : 'other' }})"></span>
                                        @endforeach
                                    </div>
                                    <p class="mt-1 text-xs text-muted">{{ $row['parties']->take(3)->map(fn ($v, $p) => $p.' '.($row['total'] ? round(100 * $v / $row['total']) : 0).'%')->implode(' · ') }}</p>
                                </td>
                                <td class="n font-semibold">{{ $row['ours'] !== null ? $row['ours'].'%' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            @endif
        </div>
    </div>
</x-layouts.app>
