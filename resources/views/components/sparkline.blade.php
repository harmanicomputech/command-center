{{-- A small trend line. $values oldest first; the last point is marked. --}}
@props(['values' => [], 'width' => 96, 'height' => 32, 'tone' => 'brand', 'label' => null])
@php
    $values = array_values(array_map('floatval', $values));
    $count = count($values);
    $min = $count ? min($values) : 0;
    $max = $count ? max($values) : 0;
    $range = $max - $min ?: 1;
    $pad = 3;
    $points = [];
    foreach ($values as $i => $value) {
        $x = $count > 1 ? $pad + $i * ($width - 2 * $pad) / ($count - 1) : $width / 2;
        $y = $height - $pad - (($value - $min) / $range) * ($height - 2 * $pad);
        $points[] = round($x, 1).','.round($y, 1);
    }
    $color = ['brand' => 'var(--brand)', 'good' => 'var(--good)', 'bad' => 'var(--bad)', 'muted' => 'var(--text-subtle)'][$tone] ?? 'var(--brand)';
    $gradient = 'spark-'.\Illuminate\Support\Str::random(6);
@endphp
@if ($count > 1)
    <svg width="{{ $width }}" height="{{ $height }}" viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="{{ $label ?? 'Trend' }}" {{ $attributes->merge(['class' => 'overflow-visible']) }}>
        <defs>
            <linearGradient id="{{ $gradient }}" x1="0" x2="0" y1="0" y2="1">
                <stop offset="0" stop-color="{{ $color }}" stop-opacity="0.22"/>
                <stop offset="1" stop-color="{{ $color }}" stop-opacity="0"/>
            </linearGradient>
        </defs>
        <polygon points="{{ $pad }},{{ $height }} {{ implode(' ', $points) }} {{ $width - $pad }},{{ $height }}" fill="url(#{{ $gradient }})"/>
        <polyline points="{{ implode(' ', $points) }}" fill="none" stroke="{{ $color }}" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
        @php [$lx, $ly] = explode(',', end($points)); @endphp
        <circle cx="{{ $lx }}" cy="{{ $ly }}" r="2.75" fill="{{ $color }}" stroke="var(--surface)" stroke-width="1.5"/>
    </svg>
@endif
