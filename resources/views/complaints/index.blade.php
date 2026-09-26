@php $maxTotal = max(1, (int) $themes->max('total')); @endphp
<x-layouts.app title="Complaints" wide>
    <x-page-header title="Complaints" eyebrow="Engage" description="What people complain about, where, and whether it’s rising: issue reports from the field plus negative narratives, over the last 8 weeks.">
        <x-slot:actions>
            <x-button :href="route('issues')" variant="secondary" icon="triangle-alert">Issues</x-button>
            <x-button :href="route('narratives')" variant="secondary" icon="radio">Narratives</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-kpi label="Complaints, 8 weeks" :value="$total" icon="message-square" />
        <x-kpi label="This week" :value="$weekTotal" icon="calendar" :delta="$lastTotal > 0 ? round(100 * ($weekTotal - $lastTotal) / $lastTotal, 1) : null" invert />
        <x-kpi label="Themes rising" :value="$rising->count()" icon="trending-up" />
    </div>

    @if ($rising->isNotEmpty())
        <section class="card mb-6 p-5">
            <h2 class="mb-3 flex items-center gap-2 text-base font-semibold"><x-icon name="trending-up" class="text-bad" /> Rising this week</h2>
            <div class="flex flex-wrap gap-2">
                @foreach ($rising as $row)
                    <a href="{{ route('complaints', ['theme' => $row['theme']]) }}" class="inline-flex items-center gap-2 rounded-full border border-line-strong px-3 py-1.5 text-sm hover:bg-surface-2">
                        <span class="font-medium">{{ $row['label'] }}</span>
                        <span class="num text-xs text-bad">{{ $row['last'] }} → {{ $row['week'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
        <section class="min-w-0 xl:col-span-3">
            <x-card title="By theme" icon="chart-column" description="Issue reports and negative narratives together. Tap a theme to map it.">
                @forelse ($themes as $row)
                    <a href="{{ route('complaints', ['theme' => $theme === $row['theme'] ? null : $row['theme']]) }}" @class(['-mx-2 grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1 rounded-lg px-2 py-2 hover:bg-surface-2 sm:grid-cols-[150px_minmax(0,1fr)_96px_auto]', 'bg-brand-softer' => $theme === $row['theme']])>
                        <span class="truncate text-sm font-medium">{{ $row['label'] }}</span>
                        <span class="num text-right text-sm font-semibold sm:order-last">{{ $row['total'] }}</span>
                        <span class="col-span-2 h-2.5 overflow-hidden rounded-full bg-surface-3 sm:col-span-1"><span class="block h-full rounded-full bg-brand" style="width: {{ round(100 * $row['total'] / $maxTotal) }}%"></span></span>
                        <span class="hidden sm:block"><x-sparkline :values="$row['spark']" :tone="$row['week'] > $row['last'] ? 'bad' : 'muted'" label="Per week, last 8 weeks" /></span>
                        <span class="col-span-2 text-xs text-subtle sm:hidden">{{ $row['issues'] }} issues · {{ $row['narratives'] }} narratives · {{ $row['week'] }} this week</span>
                    </a>
                @empty
                    <x-empty icon="message-square" title="No complaints yet" description="Issue reports and negative narrative reports from the last 8 weeks show here." compact />
                @endforelse
            </x-card>
        </section>
        <section class="min-w-0 space-y-6 xl:col-span-2">
            <x-lga-map :map="$map" :title="$themeLabel ? $themeLabel.' by LGA' : 'Complaints by LGA'" description="Last 8 weeks." />
            @if ($lgas->isNotEmpty())
                <x-card title="Top LGAs" icon="map-pin">
                    @foreach ($lgas->take(6) as $row)
                        <div class="flex justify-between border-b border-line py-2 text-sm last:border-0"><span>{{ $row['name'] }}</span><span class="num font-semibold">{{ $row['n'] }}</span></div>
                    @endforeach
                </x-card>
            @endif
        </section>
    </div>
</x-layouts.app>
