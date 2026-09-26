@php
    $link = fn (array $change) => route('areas', array_filter([...request()->only('zone', 'lga', 'sort'), ...$change]));
@endphp
<x-layouts.app title="Map & wards" wide>
    <x-page-header title="Map & wards" eyebrow="Intelligence"
        description="Every ward’s zone, what it rests on, and its priority. Our share blends past results, canvassing and surveys; weights and zone lines are in Settings.">
        <x-slot:actions>
            @if (Route::has('results') && auth()->user()->hasRole(\App\Enums\UserRole::Admin, \App\Enums\UserRole::Strategist))
                <x-button :href="route('results')" variant="secondary" icon="vote">Past results</x-button>
                <x-button :href="route('presets')" variant="secondary" icon="sliders-horizontal">LGA presets</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if (! $party)
        <x-alert tone="info" title="Zones rest on canvassing alone for now" class="mb-6">
            Set the campaign’s party in <a href="{{ route('settings') }}" class="link">Settings</a> and import the 2019 and 2023 results to add the baseline.
        </x-alert>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
        <div class="xl:col-span-3">
            @if ($map['ward'])
                <x-ward-map :map="$map['ward']" title="Wards" />
            @else
                <x-lga-map :map="$map['lga']" description="Tap an LGA to see its wards." />
            @endif
        </div>
        <div class="grid content-start gap-4 xl:col-span-2">
            <div class="grid grid-cols-2 gap-3">
                @foreach (\App\Services\Intelligence::ZONES as $key => $info)
                    <a href="{{ $link(['zone' => $zone === $key ? null : $key]) }}" class="card card-interactive p-4 {{ $zone === $key ? 'ring-2 ring-brand' : '' }}">
                        <p class="flex items-center gap-2 text-sm font-medium text-muted"><span class="size-2.5 rounded-full" style="background: {{ $info['color'] }}"></span>{{ $info['label'] }}</p>
                        <p class="num mt-1 text-3xl font-bold tracking-tight">{{ $zoneCounts[$key] ?? 0 }}</p>
                        <p class="text-xs text-subtle">wards</p>
                    </a>
                @endforeach
            </div>
            <x-table title="LGAs">
                <thead><tr><th>LGA</th><th>Zone</th><th class="n">Share</th></tr></thead>
                <tbody>
                    @foreach ($lgaRows as $row)
                        <tr>
                            <td><a href="{{ route('areas.lga', $row['slug']) }}" class="font-semibold hover:text-brand-fg">{{ $row['name'] }}</a>@if ($row['tag'])<p class="text-xs text-subtle">{{ $row['tag'] }}</p>@endif</td>
                            <td><x-badge :tone="\App\Services\Intelligence::ZONES[$row['zone']]['tone']" dot>{{ \App\Services\Intelligence::ZONES[$row['zone']]['label'] }}</x-badge></td>
                            <td class="n">{{ $row['share'] !== null ? $row['share'].'%' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </div>
    </div>

    <div class="mt-8 mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <h2 class="text-lg font-semibold">Wards <span class="num text-base font-normal text-muted">{{ $wards->count() }}</span></h2>
        <form method="get" action="{{ route('areas') }}" class="flex flex-wrap gap-2">
            @if ($zone)<input type="hidden" name="zone" value="{{ $zone }}">@endif
            <select name="lga" class="input w-44" aria-label="LGA" onchange="this.form.submit()">
                <option value="">All LGAs</option>
                @foreach ($lgas as $option)<option value="{{ $option->slug }}" @selected($lga?->id === $option->id)>{{ $option->name }}</option>@endforeach
            </select>
            <select name="sort" class="input w-44" aria-label="Sort" onchange="this.form.submit()">
                @foreach (['priority' => 'By priority', 'share' => 'By our share', 'registered' => 'By registered voters', 'canvassed' => 'By canvassed', 'name' => 'By name'] as $key => $label)<option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>@endforeach
            </select>
        </form>
    </div>

    <x-table>
        <thead>
            <tr><th>Ward</th><th>Zone</th><th class="w-44">Our share</th><th class="hidden lg:table-cell">Rests on</th><th class="n hidden md:table-cell">Canvassed</th><th class="n hidden sm:table-cell">Trend</th><th class="n">Priority</th></tr>
        </thead>
        <tbody>
            @forelse ($wards->take(request()->boolean('all') ? 500 : 40) as $row)
                <tr>
                    <td class="whitespace-nowrap"><a href="{{ route('areas.ward', [$row['lga_slug'], $row['slug']]) }}" class="font-semibold hover:text-brand-fg">{{ $row['name'] }}</a><p class="text-xs text-subtle">{{ $row['lga'] }}</p></td>
                    <td><x-badge :tone="\App\Services\Intelligence::ZONES[$row['zone']]['tone']" dot>{{ \App\Services\Intelligence::ZONES[$row['zone']]['label'] }}</x-badge></td>
                    <td>
                        @if ($row['share'] !== null)
                            <div class="flex items-center gap-2"><div class="bar flex-1"><span style="width: {{ $row['share'] }}%; background: {{ \App\Services\Intelligence::ZONES[$row['zone']]['color'] }}"></span></div><span class="num w-12 text-right text-sm font-semibold">{{ $row['share'] }}%</span></div>
                        @else
                            <span class="text-subtle">—</span>
                        @endif
                    </td>
                    <td class="hidden max-w-xs text-xs text-muted lg:table-cell">{{ $row['basis'] }}</td>
                    <td class="n hidden md:table-cell">{{ number_format($row['canvassed']) }}</td>
                    <td class="n hidden sm:table-cell"><x-delta :value="$row['trend']" suffix=" pts" empty="—" /></td>
                    <td class="n font-semibold">{{ number_format($row['priority']) }}</td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty icon="map" title="No wards match" compact /></td></tr>
            @endforelse
        </tbody>
        @if ($wards->count() > 40 && ! request()->boolean('all'))
            <x-slot:footer><a href="{{ $link(['all' => 1]) }}" class="link">Show all {{ $wards->count() }} wards</a></x-slot:footer>
        @endif
    </x-table>
</x-layouts.app>
