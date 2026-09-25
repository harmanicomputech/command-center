{{-- A dialog. Open with $dispatch('open-modal', 'name'); closes on Esc or the scrim. --}}
@props(['name', 'title' => null, 'width' => 'max-w-lg'])
<div x-data="{ open: false }" x-on:open-modal.window="open = ($event.detail === '{{ $name }}')" x-on:close-modal.window="open = false"
    x-on:keydown.escape.window="open = false" x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-6" role="dialog" aria-modal="true" @if ($title) aria-label="{{ $title }}" @endif>
    <div x-show="open" x-transition.opacity class="absolute inset-0" style="background: var(--scrim)" x-on:click="open = false"></div>
    <div x-show="open" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95" x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
        x-transition:leave="transition duration-150 ease-in" x-transition:leave-end="translate-y-4 opacity-0 sm:translate-y-0 sm:scale-95"
        class="relative w-full {{ $width }} rounded-t-3xl border border-line bg-surface p-6 shadow-overlay sm:rounded-card pb-safe" x-trap.noscroll="open">
        @if ($title)
            <div class="mb-4 flex items-start justify-between gap-4">
                <h2 class="text-lg font-semibold">{{ $title }}</h2>
                <button type="button" class="btn btn-ghost btn-icon btn-sm -mt-1 -mr-2" x-on:click="open = false" aria-label="Close"><x-icon name="x" /></button>
            </div>
        @endif
        {{ $slot }}
    </div>
</div>
