{{-- The Command Center: sidebar on desktop, drawer on tablets, tab bar on phones. --}}
@props(['title' => null, 'wide' => false])
@php
    $user = auth()->user();
    $sections = \App\Support\Navigation::sections($user);
    $tabs = \App\Support\Navigation::tabs($user);
    $isActive = fn (array $patterns) => request()->routeIs(...$patterns);
@endphp
<x-layouts.base :title="$title" x-data="{ drawer: false }" x-on:keydown.escape.window="drawer = false">
    {{-- Sidebar (desktop) and drawer (tablet / phone "More") share one nav. --}}
    <div x-show="drawer" x-cloak x-transition.opacity class="fixed inset-0 z-40 lg:hidden" style="background: var(--scrim)" x-on:click="drawer = false"></div>
    <aside id="sidebar" aria-label="Main"
        class="fixed inset-y-0 left-0 z-50 flex w-[272px] -translate-x-full flex-col border-r border-line bg-sidebar transition-transform duration-200 ease-out lg:z-30 lg:w-[256px] lg:translate-x-0"
        :class="drawer && 'translate-x-0 shadow-overlay'">
        <div class="flex h-16 flex-none items-center gap-3 px-5">
            <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3">
                <x-logo :size="34" />
                <span class="min-w-0 leading-tight">
                    <span class="block truncate text-[15px] font-bold tracking-tight">{{ $appName }}</span>
                    <span class="block truncate text-xs text-subtle">Ebonyi {{ \Illuminate\Support\Carbon::parse(config('campaign.election_date'))->format('Y') }} · {{ max(0, \App\Support\Time::daysToElection()) }} days to go</span>
                </span>
            </a>
            <button type="button" class="btn btn-ghost btn-icon btn-sm ml-auto lg:hidden" x-on:click="drawer = false" aria-label="Close menu"><x-icon name="x" /></button>
        </div>

        <div class="px-3 pb-2">
            <button type="button" x-on:click="$dispatch('open-palette'); drawer = false"
                class="flex h-9 w-full items-center gap-2.5 rounded-[10px] border border-line bg-surface px-3 text-sm text-subtle shadow-xs transition-colors hover:border-line-strong hover:text-muted">
                <x-icon name="search" size="16" />
                <span class="flex-1 text-left">Search…</span>
                <span class="hidden items-center gap-0.5 lg:flex"><kbd class="kbd">⌘</kbd><kbd class="kbd">K</kbd></span>
            </button>
        </div>

        <nav class="no-scrollbar flex-1 overflow-y-auto px-3 pt-2 pb-4">
            @foreach ($sections as $section => $items)
                <p class="eyebrow mt-4 mb-1.5 px-2.5 first:mt-1">{{ $section }}</p>
                <div class="space-y-0.5">
                    @foreach ($items as $item)
                        <a href="{{ route($item['route']) }}" class="nav-item" @if ($isActive($item['active'])) aria-current="page" @endif>
                            <x-icon :name="$item['icon']" />
                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>

        <div class="flex-none border-t border-line p-3">
            <x-dropdown align="left" width="w-[232px]" direction="up">
                <x-slot:trigger>
                    <button type="button" class="flex w-full items-center gap-3 rounded-[10px] p-2 text-left transition-colors hover:bg-surface-2">
                        <x-avatar :name="$user->name" :size="34" />
                        <span class="min-w-0 flex-1 leading-tight">
                            <span class="block truncate text-sm font-semibold">{{ $user->name }}</span>
                            <span class="block truncate text-xs text-subtle">{{ $user->role->label() }}</span>
                        </span>
                        <x-icon name="chevron-up" size="16" class="text-subtle" />
                    </button>
                </x-slot:trigger>
                    <div class="px-2.5 pt-1.5 pb-2 text-xs text-subtle">{{ $user->areaLabel() }}</div>
                    <a class="menu-item" href="{{ route('account') }}"><x-icon name="circle-user" />My account</a>
                    <button type="button" class="menu-item" x-on:click="$store.theme.toggle()"><x-icon name="moon" />Switch to <span x-text="$store.theme.dark ? 'light' : 'dark'">dark</span> mode</button>
                    <button type="button" class="menu-item" x-show="$store.install.available" x-cloak x-on:click="$store.install.prompt()"><x-icon name="download" />Install the app</button>
                    <div class="my-1 h-px bg-line"></div>
                    <form method="post" action="{{ route('logout') }}" data-logout>@csrf<button type="submit" class="menu-item"><x-icon name="log-out" />Log out</button></form>
            </x-dropdown>
        </div>
    </aside>

    <div class="lg:pl-[256px]">
        {{-- Top bar --}}
        <header data-topbar class="no-print sticky top-0 z-20 border-b border-line bg-bg/85 backdrop-blur-xl supports-[backdrop-filter]:bg-bg/70">
            <div class="mx-auto flex h-14 max-w-[1440px] items-center gap-2 px-4 sm:px-6 lg:h-16 lg:px-8">
                <button type="button" class="btn btn-ghost btn-icon -ml-2 hidden md:inline-flex lg:hidden" x-on:click="drawer = true" aria-label="Open menu" aria-controls="sidebar"><x-icon name="menu" /></button>
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 lg:hidden">
                    <x-logo :size="30" />
                    <span class="text-[15px] font-bold tracking-tight">{{ $appName }}</span>
                </a>

                <button type="button" x-on:click="$dispatch('open-palette')"
                    class="ml-auto hidden h-10 w-full max-w-sm items-center gap-2.5 rounded-[10px] border border-line bg-surface px-3.5 text-sm text-subtle shadow-xs transition-colors hover:border-line-strong md:flex lg:ml-0">
                    <x-icon name="search" size="17" />
                    <span class="flex-1 text-left">Search people, wards, LGAs…</span>
                    <span class="hidden items-center gap-0.5 lg:flex"><kbd class="kbd">⌘</kbd><kbd class="kbd">K</kbd></span>
                </button>

                <div class="ml-auto flex items-center gap-1">
                    <button type="button" class="btn btn-ghost btn-icon md:hidden" x-on:click="$dispatch('open-palette')" aria-label="Search"><x-icon name="search" /></button>
                    <span x-show="! $store.network.online" x-cloak class="badge badge-warn mr-1"><x-icon name="wifi-off" />Offline</span>
                    <button type="button" class="btn btn-ghost btn-icon" x-on:click="$store.theme.toggle()" :aria-label="$store.theme.dark ? 'Switch to light mode' : 'Switch to dark mode'" aria-label="Switch theme">
                        <x-icon name="moon" x-show="! $store.theme.dark" />
                        <x-icon name="sun" x-show="$store.theme.dark" x-cloak />
                    </button>
                    <a href="{{ route('account') }}" class="ml-1 lg:hidden"><x-avatar :name="$user->name" :size="32" /><span class="sr-only">My account</span></a>
                </div>
            </div>
        </header>

        <main id="main" @class(['mx-auto px-4 pt-6 pb-28 sm:px-6 md:pb-12 lg:px-8 lg:pt-8', 'max-w-[1440px]' => $wide, 'max-w-[1200px]' => ! $wide])>
            @if (\App\Support\Settings::get('demo.loaded_at'))
                <p class="mb-5 flex items-center gap-2 rounded-xl bg-accent-soft px-4 py-2.5 text-sm text-ink" role="note"><x-icon name="sparkles" size="16" class="flex-none text-accent-fg" /><span><strong>Demo data is loaded.</strong> Figures include fictional records.
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('system') }}#demo" class="font-medium underline">Remove it</a> before real use.
                    @endif
                </span></p>
            @endif
            {{ $slot }}
        </main>
    </div>

    {{-- Phone tab bar: four places and More (the drawer). --}}
    <nav class="tabbar no-print md:hidden" aria-label="Main">
        @foreach ($tabs as $tab)
            <a href="{{ route($tab['route']) }}" class="tab" @if ($isActive($tab['active'])) aria-current="page" @endif>
                <x-icon :name="$tab['icon']" />
                <span>{{ $tab['label'] }}</span>
            </a>
        @endforeach
        @for ($i = count($tabs); $i < 4; $i++)<span></span>@endfor
        <button type="button" class="tab" x-on:click="drawer = true" aria-controls="sidebar">
            <x-icon name="ellipsis" />
            <span>More</span>
        </button>
    </nav>

    <x-palette :commands="\App\Support\Navigation::commands($user)" :search-url="route('search')" />
</x-layouts.base>
