{{-- The command palette (⌘K). Commands are pages; the search endpoint adds people, wards and LGAs. --}}
@props(['commands' => [], 'searchUrl' => null])
<div x-data="palette(@js($commands), @js($searchUrl))" x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-start justify-center px-3 pt-[10vh] sm:pt-[14vh]" role="dialog" aria-modal="true" aria-label="Search and jump">
    <div class="absolute inset-0" style="background: var(--scrim)" x-show="open" x-transition.opacity x-on:click="close()"></div>
    <div class="relative w-full max-w-xl overflow-hidden rounded-2xl border border-line bg-surface shadow-overlay" x-show="open"
        x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="opacity-0 scale-[0.97] -translate-y-2" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition duration-100 ease-in" x-transition:leave-end="opacity-0 scale-[0.98]" x-trap.noscroll="open">
        <div class="flex items-center gap-3 border-b border-line px-4">
            <x-icon name="search" class="flex-none text-subtle" />
            <input x-ref="input" x-model="query" x-on:input="search()" x-on:keydown.down.prevent="move(1)" x-on:keydown.up.prevent="move(-1)"
                x-on:keydown.enter.prevent="go()" x-on:keydown.escape.prevent="close()" type="text" placeholder="Search people, wards, LGAs or pages"
                class="h-14 w-full min-w-0 bg-transparent text-base outline-none placeholder:text-subtle" aria-label="Search" autocomplete="off" spellcheck="false">
            <span x-show="loading" class="flex-none text-subtle"><x-icon name="loader-circle" class="animate-spin" size="18" /></span>
            <kbd class="kbd hidden sm:inline-flex">Esc</kbd>
        </div>
        <ul x-ref="list" class="max-h-[min(60vh,420px)] overflow-y-auto p-2" role="listbox">
            <template x-for="(item, index) in results" :key="item.url + index">
                <li :data-index="index" role="option" :aria-selected="index === active">
                    <a :href="item.url" x-on:mouseenter="active = index" x-on:click.prevent="go(item)"
                        class="flex items-center gap-3 rounded-[10px] px-3 py-2.5 text-sm" :class="index === active ? 'bg-brand-soft text-ink' : 'text-ink'">
                        <span class="grid size-8 flex-none place-items-center rounded-lg border border-line bg-surface text-muted" :class="index === active && 'text-brand-fg border-transparent'">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" x-html="item.icon"></svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium" x-text="item.label"></span>
                            <span class="block truncate text-xs text-subtle" x-text="item.detail || item.group"></span>
                        </span>
                        <span x-show="index === active" class="flex-none text-subtle"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 10 4 15l5 5"/><path d="M20 4v7a4 4 0 0 1-4 4H4"/></svg></span>
                    </a>
                </li>
            </template>
            <li x-show="results.length === 0" class="px-3 py-10 text-center text-sm text-muted">
                <span x-show="! loading">Nothing matches “<span x-text="query"></span>”.</span>
                <span x-show="loading">Searching…</span>
            </li>
        </ul>
        <div class="hidden items-center gap-4 border-t border-line bg-surface-2 px-4 py-2.5 text-xs text-subtle sm:flex">
            <span class="flex items-center gap-1.5"><kbd class="kbd">↑</kbd><kbd class="kbd">↓</kbd> to move</span>
            <span class="flex items-center gap-1.5"><kbd class="kbd">↵</kbd> to open</span>
            <span class="ml-auto">Results respect your area</span>
        </div>
    </div>
</div>
