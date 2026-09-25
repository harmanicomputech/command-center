{{-- Initials on a colour picked from the name (stable per person). --}}
@props(['name' => '?', 'size' => 36])
@php
    $parts = preg_split('/\s+/', trim($name)) ?: ['?'];
    $initials = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($parts[0], 0, 1).(count($parts) > 1 ? \Illuminate\Support\Str::substr(end($parts), 0, 1) : ''));
    $tones = [
        'bg-brand-soft text-brand-fg',
        'bg-accent-soft text-accent-fg',
        'bg-info-soft text-info',
        'bg-good-soft text-good',
        'bg-warn-soft text-warn',
        'bg-surface-3 text-ink',
    ];
    $tone = $tones[crc32($name) % count($tones)];
@endphp
<span {{ $attributes->merge(['class' => "inline-grid flex-none place-items-center rounded-full font-semibold select-none {$tone}"]) }} style="width: {{ $size }}px; height: {{ $size }}px; font-size: {{ max(11, round($size * 0.38)) }}px" aria-hidden="true">{{ $initials }}</span>
