<x-layouts.base title="Offline">
    <main id="main" class="grid min-h-dvh place-items-center px-4">
        <div class="w-full max-w-sm text-center">
            <div class="relative mx-auto mb-6 grid size-24 place-items-center" aria-hidden="true">
                <span class="absolute inset-0 rounded-full bg-brand-softer"></span>
                <span class="absolute inset-3 rounded-full bg-brand-soft"></span>
                <span class="relative grid size-14 place-items-center rounded-2xl border border-line bg-surface text-brand-fg shadow-card"><x-icon name="wifi-off" size="26" /></span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight">You’re offline</h1>
            <p class="mt-2 text-sm text-muted">This page hasn’t been saved on this device yet. Anything you registered is safe on your phone and will sync when the network returns.</p>
            <div class="mt-6 flex flex-col gap-2">
                <a href="/field" class="btn btn-primary btn-lg w-full">Open the field app</a>
                <button type="button" class="btn btn-secondary btn-lg w-full" onclick="location.reload()">Try again</button>
            </div>
            <p class="mt-8 flex items-center justify-center gap-2 text-xs text-subtle"><x-logo :size="18" /> {{ $appName }}</p>
        </div>
    </main>
</x-layouts.base>
