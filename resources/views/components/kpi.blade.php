{{-- A KPI tile: big number, change against last week, sparkline. --}}
@props([
    'label',
    'value' => 0,
    'decimals' => 0,
    'suffix' => '',
    'delta' => null,
    'deltaLabel' => 'vs last week',
    'invert' => false,
    'spark' => [],
    'icon' => null,
    'href' => null,
    'hint' => null,
])
@php($tag = $href ? 'a' : 'div')
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'card flex min-w-0 flex-col gap-3 p-5'.($href ? ' card-interactive' : '')]) }}>
    <div class="flex items-center justify-between gap-2">
        <p class="line-clamp-2 text-sm font-medium text-muted">{{ $label }}</p>
        @if ($icon)<span class="grid size-8 flex-none place-items-center rounded-lg bg-surface-2 text-muted"><x-icon :name="$icon" size="17" /></span>@endif
    </div>
    <div class="flex items-end justify-between gap-3">
        <p class="num text-3xl font-bold tracking-tight"><span x-data x-count="{{ $value }}" data-decimals="{{ $decimals }}" data-suffix="{{ $suffix }}">{{ number_format($value, $decimals) }}{{ $suffix }}</span></p>
        <x-sparkline :values="$spark" :tone="$delta === null ? 'muted' : (($invert ? $delta <= 0 : $delta >= 0) ? 'good' : 'bad')" :label="$label.' trend'" class="mb-1 flex-none" />
    </div>
    @if ($delta !== null || $hint)
        <div class="flex flex-wrap items-center justify-between gap-2">
            @if ($delta !== null)<x-delta :value="$delta" :label="$deltaLabel" :invert="$invert" />@endif
            @if ($hint)<span class="text-xs text-muted">{{ $hint }}</span>@endif
        </div>
    @endif
</{{ $tag }}>
