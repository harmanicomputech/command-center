{{-- A small labelled figure. --}}
@props(['label', 'value', 'hint' => null])
<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <p class="text-xs font-medium text-muted">{{ $label }}</p>
    <p class="num mt-1 text-xl font-semibold tracking-tight">{{ $value }}</p>
    @if ($hint)<p class="mt-0.5 text-xs text-subtle">{{ $hint }}</p>@endif
</div>
