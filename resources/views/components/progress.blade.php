@props(['value' => 0, 'max' => 100, 'tone' => 'brand', 'label' => null])
@php($percent = $max > 0 ? min(100, round(100 * $value / $max, 1)) : 0)
<div {{ $attributes->merge(['class' => 'bar']) }} role="progressbar" aria-valuenow="{{ $value }}" aria-valuemin="0" aria-valuemax="{{ $max }}" @if ($label) aria-label="{{ $label }}" @endif>
    <span style="width: {{ $percent }}%; background: var(--{{ $tone }})"></span>
</div>
