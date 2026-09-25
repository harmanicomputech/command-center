@props([
    'variant' => 'primary',
    'size' => null,
    'icon' => null,
    'iconRight' => null,
    'href' => null,
    'type' => 'submit',
    'square' => false,
])
@php
    $classes = collect(['btn', 'btn-'.$variant, $size ? 'btn-'.$size : null, $square ? 'btn-icon' : null])->filter()->implode(' ');
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" />@endif
        {{ $slot }}
        @if ($iconRight)<x-icon :name="$iconRight" />@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" />@endif
        {{ $slot }}
        @if ($iconRight)<x-icon :name="$iconRight" />@endif
    </button>
@endif
