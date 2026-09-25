@props(['tone' => null, 'dot' => false, 'icon' => null])
<span {{ $attributes->merge(['class' => collect(['badge', $tone ? 'badge-'.$tone : null, $dot ? 'badge-dot' : null])->filter()->implode(' ')]) }}>
    @if ($icon)<x-icon :name="$icon" />@endif{{ $slot }}
</span>
