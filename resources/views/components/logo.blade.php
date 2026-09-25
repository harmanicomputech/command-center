{{-- The app mark: brand tile with a radar arc and a gold signal. Follows the tokens. --}}
@props(['size' => 36])
<span {{ $attributes->merge(['class' => 'relative inline-grid flex-none place-items-center overflow-hidden']) }}
    style="width: {{ $size }}px; height: {{ $size }}px; border-radius: {{ round($size * 0.3) }}px; background: linear-gradient(145deg, color-mix(in oklab, var(--brand), white 14%), var(--brand) 50%, color-mix(in oklab, var(--brand), black 22%)); box-shadow: inset 0 1px 0 rgb(255 255 255 / .18), 0 1px 2px rgb(0 0 0 / .18)" aria-hidden="true">
    <svg width="{{ round($size * 0.62) }}" height="{{ round($size * 0.62) }}" viewBox="0 0 24 24" fill="none">
        <path d="M19.5 8.2A8.5 8.5 0 1 0 19.5 15.8" stroke="white" stroke-width="2.4" stroke-linecap="round"/>
        <path d="M15.6 9.9a4.3 4.3 0 1 0 0 4.2" stroke="white" stroke-opacity=".7" stroke-width="2.2" stroke-linecap="round"/>
        <circle cx="19.6" cy="12" r="2.3" fill="var(--accent)"/>
    </svg>
</span>
