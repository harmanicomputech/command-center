@props(['title' => null])
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#f7f6f3" data-light="#f7f6f3">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ $appName }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon-32.png" sizes="32x32">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ $appName }}">
    <link rel="preload" href="/fonts/inter-latin.woff2" as="font" type="font/woff2" crossorigin>
    <script>
        (function () {
            var choice = 'system';
            try { choice = localStorage.getItem('cc-theme') || 'system'; } catch (e) {}
            var dark = choice === 'dark' || (choice === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.dataset.theme = dark ? 'dark' : 'light';
            document.querySelector('meta[name="theme-color"]').content = dark ? '#0e0e0d' : '#f7f6f3';
        })();
    </script>
    @auth<script src="/outbox.js"></script>@endauth
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body {{ $attributes->merge(['class' => 'bg-bg text-ink antialiased']) }}>
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[60] focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2 focus:shadow-overlay">Skip to content</a>

    {{ $slot }}

    @php
        $flash = array_values(array_filter([
            session('status') ? ['message' => session('status'), 'tone' => 'good'] : null,
            session('error') ? ['message' => session('error'), 'tone' => 'bad'] : null,
        ]));
    @endphp
    <div x-data="toasts(@js($flash))" class="pointer-events-none fixed inset-x-0 top-3 z-[70] flex flex-col items-center gap-2 px-4 sm:top-auto sm:right-6 sm:bottom-6 sm:left-auto sm:items-end" aria-live="polite">
        <template x-for="item in items" :key="item.id">
            <div class="toast pointer-events-auto" x-transition:enter="transition duration-300 ease-out" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition duration-200 ease-in" x-transition:leave-end="opacity-0" :role="item.tone === 'bad' ? 'alert' : 'status'">
                <span class="mt-px grid size-6 flex-none place-items-center rounded-full" :class="item.tone === 'bad' ? 'bg-bad-soft text-bad' : 'bg-good-soft text-good'">
                    <svg x-show="item.tone !== 'bad'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                    <svg x-show="item.tone === 'bad'" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" aria-hidden="true"><path d="M12 8v5M12 16.5v.5"/></svg>
                </span>
                <p class="min-w-0 flex-1 text-ink" x-text="item.message"></p>
                <button type="button" class="-m-1 grid size-7 flex-none place-items-center rounded-md text-subtle hover:bg-surface-2 hover:text-ink" x-on:click="remove(item.id)" aria-label="Dismiss">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
        </template>
    </div>
</body>
</html>
