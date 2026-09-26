{{-- The schematic LGA tile map. $map from App\Services\LgaMap::build(). Layers switch without a reload. --}}
@props(['map', 'title' => 'Ebonyi by LGA', 'description' => null])
@php
    $keys = array_keys($map['layers']);
@endphp
<section {{ $attributes->merge(['class' => 'card min-w-0 p-5 sm:p-6']) }} x-data="{ layer: @js($keys[0]) }">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-base font-semibold">{{ $title }}</h2>
            @if ($description)<p class="mt-0.5 text-sm text-muted">{{ $description }}</p>@endif
        </div>
        @if (count($keys) > 1)
            <div class="flex flex-wrap gap-1 rounded-[10px] bg-surface-2 p-1" role="tablist" aria-label="Map layer">
                @foreach ($map['layers'] as $key => $layer)
                    <button type="button" role="tab" x-on:click="layer = '{{ $key }}'" :aria-selected="layer === '{{ $key }}'"
                        class="h-8 rounded-lg px-3 text-[13px] font-semibold transition-colors" :class="layer === '{{ $key }}' ? 'bg-surface text-ink shadow-xs' : 'text-muted hover:text-ink'">{{ $layer['label'] }}</button>
                @endforeach
            </div>
        @endif
    </div>

    <div class="grid grid-cols-4 gap-1.5 sm:gap-2" style="grid-template-rows: repeat(5, minmax(64px, auto))">
        @foreach ($map['tiles'] as $tile)
            @php
                $tag = $tile['allowed'] && $tile['slug'] ? 'a' : 'div';
                $styles = [];
                foreach ($keys as $key) {
                    $cell = $tile['cells'][$key];
                    $step = $cell['step'] ?? 0;
                    $fill = $cell['color'] ?? "var(--seq-{$step})";
                    $ink = isset($cell['color']) ? ($cell['ink'] ?? 'var(--text)') : ($step >= 4 ? 'var(--on-seq-strong)' : 'var(--text)');
                    $styles[$key] = "grid-column: {$tile['col']}; grid-row: {$tile['row']}; background: {$fill}; color: {$ink}";
                }
            @endphp
            <{{ $tag }} @if ($tag === 'a') href="{{ route('areas.lga', $tile['slug']) }}" @endif
                style="{{ $styles[$keys[0]] }}" x-bind:style="@js($styles)[layer]"
                class="group relative flex min-w-0 flex-col justify-between overflow-hidden rounded-xl p-2 transition-transform duration-200 sm:p-3 {{ $tag === 'a' ? 'hover:-translate-y-0.5 hover:shadow-raised' : 'opacity-45' }}"
                title="{{ $tile['name'] }}">
                <span class="truncate text-[11px] leading-tight font-semibold sm:text-[13px]">{{ $tile['name'] }}</span>
                @foreach ($keys as $key)
                    <span x-show="layer === '{{ $key }}'" @if (! $loop->first) x-cloak @endif class="num text-sm font-bold tracking-tight sm:text-lg">{{ $tile['cells'][$key]['value'] }}</span>
                @endforeach
            </{{ $tag }}>
        @endforeach
    </div>

    @foreach ($map['layers'] as $key => $layer)
        <div x-show="layer === '{{ $key }}'" @if (! $loop->first) x-cloak @endif class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-muted">
            <span class="flex flex-wrap items-center gap-2">
                @if ($layer['categorical'] ?? false)
                    @foreach ($layer['legend'] as [$color, $text])
                        <span class="flex items-center gap-1.5"><i class="block size-2.5 rounded" style="background: {{ $color }}"></i>{{ $text }}</span>
                    @endforeach
                @elseif (count($layer['legend']) === 2)
                    <span>{{ $layer['legend'][0][1] }}</span>
                    <span class="flex overflow-hidden rounded">@for ($s = 1; $s <= 5; $s++)<i class="block h-2.5 w-5" style="background: var(--seq-{{ $s }})"></i>@endfor</span>
                    <span>{{ $layer['legend'][1][1] }}</span>
                @else
                    <i class="block size-2.5 rounded" style="background: var(--seq-0)"></i> {{ $layer['legend'][0][1] }}
                @endif
            </span>
            <span class="text-subtle">Schematic: tiles sit roughly where each LGA lies, north at the top. Not to scale.</span>
        </div>
    @endforeach
</section>
