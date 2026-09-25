{{-- A progress ring: $value of $max, with the figure in the middle. --}}
@props(['value' => 0, 'max' => 100, 'size' => 120, 'stroke' => 10, 'label' => null])
@php
    $ratio = $max > 0 ? min(1, $value / $max) : 0;
    $radius = ($size - $stroke) / 2;
    $circumference = 2 * M_PI * $radius;
@endphp
<div {{ $attributes->merge(['class' => 'relative inline-grid flex-none place-items-center']) }} style="width: {{ $size }}px; height: {{ $size }}px"
    role="img" aria-label="{{ $label ?? 'Progress' }}: {{ number_format($value) }} of {{ number_format($max) }}">
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}" class="-rotate-90">
        <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $radius }}" fill="none" stroke="var(--surface-3)" stroke-width="{{ $stroke }}"/>
        <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $radius }}" fill="none" stroke="url(#ring-gradient)" stroke-width="{{ $stroke }}" stroke-linecap="round"
            stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $circumference * (1 - $ratio) }}"
            style="transition: stroke-dashoffset 900ms var(--ease-out)"/>
        <defs>
            <linearGradient id="ring-gradient" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="var(--brand)"/>
                <stop offset="1" stop-color="color-mix(in oklab, var(--brand), var(--accent) 45%)"/>
            </linearGradient>
        </defs>
    </svg>
    <div class="absolute inset-0 grid place-content-center text-center">
        {{ $slot }}
    </div>
</div>
