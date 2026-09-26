@props(['level'])
@php
    [$label, $tone] = \App\Services\Structure::LABELS[$level] ?? ['Unknown', null];
@endphp
<x-badge :tone="$tone" dot {{ $attributes }}>{{ $label }}</x-badge>
