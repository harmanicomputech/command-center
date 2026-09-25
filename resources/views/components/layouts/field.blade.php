{{-- The Field Force app: phone first, a bottom tab bar with the big Register button. --}}
@props(['title' => null, 'back' => null])
@php
    $user = auth()->user();
    $tabs = [
        ['field.home', 'Home', 'house'],
        ['field.tasks', 'Tasks', 'list-todo'],
        ['field.register', 'Register', 'plus'],
        ['field.issues', 'Issues', 'triangle-alert'],
        ['field.me', 'Me', 'circle-user'],
    ];
@endphp
<x-layouts.base :title="$title" class="field-app">
    <header data-topbar class="no-print sticky top-0 z-20 border-b border-line bg-bg/85 backdrop-blur-xl supports-[backdrop-filter]:bg-bg/70">
        <div class="mx-auto flex h-14 max-w-xl items-center gap-3 px-4">
            @if ($back)
                <a href="{{ $back }}" class="btn btn-ghost btn-icon -ml-2" aria-label="Back"><x-icon name="arrow-left" /></a>
                <p class="min-w-0 flex-1 truncate text-base font-semibold">{{ $title }}</p>
            @else
                <a href="{{ route('field.home') }}" class="flex min-w-0 flex-1 items-center gap-2.5">
                    <x-logo :size="30" />
                    <span class="hidden truncate text-[15px] font-bold tracking-tight min-[420px]:inline">{{ $appName }}</span>
                </a>
            @endif
            <x-sync-pill />
        </div>
    </header>

    <main id="main" class="mx-auto max-w-xl px-4 pt-5 pb-32">
        {{ $slot }}
    </main>

    <nav class="tabbar no-print" aria-label="Main">
        @foreach ($tabs as [$route, $label, $icon])
            @if ($route === 'field.register')
                <a href="{{ route($route) }}" class="tab-hero" @if (request()->routeIs($route)) aria-current="page" @endif>
                    <span class="tab-hero-button"><x-icon :name="$icon" /></span>
                    <span>{{ $label }}</span>
                </a>
            @else
                <a href="{{ route($route) }}" class="tab" @if (request()->routeIs($route.'*')) aria-current="page" @endif>
                    <x-icon :name="$icon" />
                    <span>{{ $label }}</span>
                </a>
            @endif
        @endforeach
    </nav>
</x-layouts.base>
