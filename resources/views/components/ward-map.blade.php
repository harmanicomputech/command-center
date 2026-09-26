{{-- The ward choropleth (SVG, no tiles: works offline and prints). $map from MapLayers::build()['ward']. --}}
@props(['map', 'title' => 'Wards'])
@php
    $wardsJson = collect($map['wards'])->map(fn ($ward) => \Illuminate\Support\Arr::except($ward, ['d']))->values();
@endphp
<section {{ $attributes->merge(['class' => 'card min-w-0 p-5 sm:p-6']) }}
    x-data="{ layer: 'zone', hover: null, wards: @js($wardsJson), pick(id) { this.hover = this.wards.find(w => w.id === id) } }">
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <h2 class="text-base font-semibold">{{ $title }}</h2>
        <div class="flex flex-wrap gap-1 rounded-[10px] bg-surface-2 p-1" role="tablist" aria-label="Map layer">
            @foreach ($map['layers'] as $key => $label)
                <button type="button" role="tab" x-on:click="layer = '{{ $key }}'" :aria-selected="layer === '{{ $key }}'"
                    class="h-8 rounded-lg px-3 text-[13px] font-semibold transition-colors" :class="layer === '{{ $key }}' ? 'bg-surface text-ink shadow-xs' : 'text-muted hover:text-ink'">{{ $label }}</button>
            @endforeach
        </div>
    </div>
    <div class="relative">
        <svg viewBox="{{ $map['viewBox'] }}" class="h-auto w-full" role="img" aria-label="Map of Ebonyi’s wards">
            @foreach ($map['wards'] as $ward)
                @php
                    $fills = collect($ward['cells'])->map(fn ($cell) => $cell['color'])->all();
                @endphp
                <path d="{{ $ward['d'] }}" fill="{{ $fills['zone'] }}" x-bind:fill="@js($fills)[layer]"
                    stroke="var(--surface)" stroke-width="1" stroke-linejoin="round"
                    class="cursor-pointer transition-[filter] duration-150 hover:brightness-110 focus:outline-none" tabindex="0"
                    x-on:mouseenter="pick({{ $ward['id'] }})" x-on:focus="pick({{ $ward['id'] }})" x-on:click="pick({{ $ward['id'] }})"
                    @if ($ward['url']) x-on:dblclick="location.href = @js($ward['url'])" x-on:keydown.enter="location.href = @js($ward['url'])" @endif>
                    <title>{{ $ward['name'] }}, {{ $ward['lga'] }}: {{ $ward['cells']['zone']['text'] }}</title>
                </path>
            @endforeach
        </svg>
        <template x-if="hover">
            <div class="pointer-events-auto absolute top-2 right-2 w-60 rounded-xl border border-line bg-surface p-4 text-sm shadow-overlay">
                <p class="font-semibold" x-text="hover.name"></p>
                <p class="text-xs text-subtle" x-text="hover.lga"></p>
                <dl class="mt-3 space-y-1.5">
                    <div class="flex justify-between gap-3"><dt class="text-muted">Zone</dt><dd class="font-medium" x-text="hover.zone + (hover.share !== null ? ' · ' + hover.share + '%' : '')"></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">Registrations</dt><dd class="num font-medium" x-text="hover.cells.registrations.text"></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">Top issue</dt><dd class="font-medium" x-text="hover.top_issue || '—'"></dd></div>
                </dl>
                <a x-show="hover.url" :href="hover.url" class="link mt-3 inline-flex items-center gap-1 text-sm">Ward profile <x-icon name="arrow-right" size="14" /></a>
            </div>
        </template>
    </div>
    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs text-muted">
        <span class="flex flex-wrap gap-3" x-show="layer === 'zone'">
            @foreach ($map['legend'] as [$color, $label])<span class="flex items-center gap-1.5"><i class="block size-2.5 rounded" style="background: {{ $color }}"></i>{{ $label }}</span>@endforeach
        </span>
        <span class="flex items-center gap-2" x-show="layer !== 'zone'" x-cloak>None <span class="flex overflow-hidden rounded">@for ($s = 1; $s <= 5; $s++)<i class="block h-2.5 w-5" style="background: var(--seq-{{ $s }})"></i>@endfor</span> Most</span>
        <span class="text-subtle">Boundaries: {{ $map['attribution'] }}. Tap a ward for details; double-tap to open it.</span>
    </div>
</section>
