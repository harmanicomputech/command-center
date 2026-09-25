{{-- An inline banner: info, good, warn or bad, with an optional title and actions. --}}
@props(['tone' => 'info', 'title' => null, 'icon' => null])
@php
    $icon ??= ['info' => 'info', 'good' => 'circle-check', 'warn' => 'triangle-alert', 'bad' => 'circle-alert'][$tone] ?? 'info';
    $classes = [
        'info' => 'bg-info-soft text-info',
        'good' => 'bg-good-soft text-good',
        'warn' => 'bg-warn-soft text-warn',
        'bad' => 'bg-bad-soft text-bad',
    ][$tone] ?? 'bg-info-soft text-info';
@endphp
<div {{ $attributes->merge(['class' => "flex gap-3 rounded-xl p-4 {$classes}"]) }} role="{{ in_array($tone, ['bad', 'warn'], true) ? 'alert' : 'status' }}">
    <x-icon :name="$icon" size="20" class="mt-px flex-none" />
    <div class="min-w-0 flex-1 text-sm text-ink">
        @if ($title)<p class="font-semibold">{{ $title }}</p>@endif
        <div @class(['mt-1' => $title, 'text-muted' => $title])>{{ $slot }}</div>
        @isset($actions)<div class="mt-3 flex flex-wrap gap-2">{{ $actions }}</div>@endisset
    </div>
</div>
