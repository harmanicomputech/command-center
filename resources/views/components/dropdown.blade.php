{{-- A menu under a trigger. Slots: trigger, and the items (links or buttons with .menu-item). --}}
@props(['align' => 'right', 'width' => 'w-56', 'direction' => 'down'])
<div x-data="{ open: false }" class="relative" x-on:keydown.escape="open = false" x-on:click.outside="open = false">
    <div x-on:click="open = ! open">{{ $trigger }}</div>
    <div x-show="open" x-cloak x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0 -translate-y-1 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition duration-100 ease-in" x-transition:leave-end="opacity-0 scale-95"
        class="absolute z-50 {{ $direction === 'up' ? 'bottom-full mb-2' : 'mt-2' }} {{ $width }} {{ $align === 'right' ? 'right-0' : 'left-0' }} {{ $direction === 'up' ? 'origin-bottom' : ($align === 'right' ? 'origin-top-right' : 'origin-top-left') }} rounded-xl border border-line bg-surface p-1.5 shadow-overlay" role="menu">
        {{ $slot }}
    </div>
</div>
