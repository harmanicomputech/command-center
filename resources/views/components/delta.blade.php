{{-- Change against a previous period: sign and arrow in text, never colour alone. --}}
@props(['value' => null, 'suffix' => '%', 'label' => null, 'invert' => false, 'empty' => 'No earlier data'])
@if ($value === null)
    <span {{ $attributes->merge(['class' => 'text-xs font-medium text-subtle']) }}>{{ $empty }}</span>
@else
    @php
        $up = $value > 0;
        $flat = abs($value) < 0.05;
        $good = $invert ? ! $up : $up;
        $tone = $flat ? 'text-muted bg-surface-2' : ($good ? 'text-good bg-good-soft' : 'text-bad bg-bad-soft');
        $text = ($flat ? '±' : ($up ? '+' : '−')).rtrim(rtrim(number_format(abs($value), 1), '0'), '.').$suffix;
    @endphp
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-xs font-medium text-muted']) }}>
        <span class="num inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 font-semibold {{ $tone }}"><span aria-hidden="true">{{ $flat ? '▬' : ($up ? '▲' : '▼') }}</span>{{ $text }}</span>
        @if ($label)<span>{{ $label }}</span>@endif
    </span>
@endif
